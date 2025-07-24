<?php
/**
 * Search Engine
 * 
 * Core search logic that combines intent classification, API queries, and result formatting
 * 
 * @package ZipPicks_Smart_Search
 */

namespace ZipPicks\SmartSearch;

class Search_Engine {
    
    /**
     * API client instance
     * @var API_Client
     */
    private $api_client;
    
    /**
     * Cache manager instance
     * @var Cache_Manager
     */
    private $cache_manager;
    
    /**
     * Analytics instance
     * @var Analytics
     */
    private $analytics;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_client = API_Client::instance();
        $this->cache_manager = Cache_Manager::instance();
        $this->analytics = Analytics::instance();
    }
    
    /**
     * Perform search
     * 
     * @param string $query Search query
     * @param array $params Additional parameters
     * @return array Search results
     */
    public function search($query, $params = []) {
        $start_time = microtime(true);
        
        // Get user context
        $context = $this->get_search_context($params);
        
        // Classify intent
        $classification = Intent_Classifier::classify($query, $context);
        
        // Generate cache key
        $cache_key = $this->generate_cache_key($query, $params, $classification);
        
        // Check cache
        $cached_results = $this->cache_manager->get($cache_key);
        if ($cached_results !== false && !isset($params['skip_cache'])) {
            $cached_results['cached'] = true;
            $this->track_search($query, $classification, $cached_results, $context, microtime(true) - $start_time);
            return $cached_results;
        }
        
        // Perform search based on intent
        $results = $this->perform_search($query, $classification, $params, $context);
        
        // Process results
        $processed_results = $this->process_results($results, $classification, $context);
        
        // Add metadata
        $search_time = microtime(true) - $start_time;
        $response = [
            'query_analysis' => $classification,
            'results' => $processed_results['results'],
            'total_results' => $processed_results['total'],
            'refinements' => $this->get_refinements($classification, $processed_results['results']),
            'meta' => [
                'total_results' => $processed_results['total'],
                'response_time_ms' => round($search_time * 1000),
                'cache_hit' => false,
                'personalized' => !empty($context['user_id']),
            ],
        ];
        
        // Cache results
        $this->cache_manager->set($cache_key, $response);
        
        // Track search
        $this->track_search($query, $classification, $response, $context, $search_time);
        
        return $response;
    }
    
    /**
     * Get search context
     * 
     * @param array $params
     * @return array
     */
    private function get_search_context($params) {
        $context = [
            'user_id' => get_current_user_id(),
            'session_id' => $this->get_session_id(),
            'location' => $this->get_user_location($params),
            'time' => current_time('mysql'),
            'device' => wp_is_mobile() ? 'mobile' : 'desktop',
        ];
        
        // Add user preferences if logged in
        if ($context['user_id']) {
            $context['preferences'] = $this->get_user_preferences($context['user_id']);
        }
        
        return $context;
    }
    
    /**
     * Perform search based on intent
     * 
     * @param string $query
     * @param array $classification
     * @param array $params
     * @param array $context
     * @return array
     */
    private function perform_search($query, $classification, $params, $context) {
        $search_params = [
            'limit' => $params['limit'] ?? 20,
            'offset' => $params['offset'] ?? 0,
            'min_confidence' => 0.5,
        ];
        
        // Add location
        if (!empty($context['location'])) {
            if (!empty($context['location']['zip'])) {
                $search_params['zip'] = $context['location']['zip'];
            } else {
                $search_params['lat'] = $context['location']['lat'];
                $search_params['lng'] = $context['location']['lng'];
                $search_params['city'] = $context['location']['city'] ?? '';
            }
            
            $search_params['radius'] = $params['radius'] ?? 10;
        }
        
        // Handle different intent types
        switch ($classification['intent']) {
            case Intent_Classifier::INTENT_VIBE:
                return $this->search_by_vibe($classification, $search_params);
                
            case Intent_Classifier::INTENT_UTILITY:
                return $this->search_by_utility($classification, $search_params, $context);
                
            case Intent_Classifier::INTENT_HYBRID:
                return $this->search_hybrid($classification, $search_params, $context);
                
            default:
                return $this->search_general($query, $search_params, $context);
        }
    }
    
    /**
     * Search by vibe
     * 
     * @param array $classification
     * @param array $params
     * @return array
     */
    private function search_by_vibe($classification, $params) {
        // Expand vibes
        $vibes = $classification['detected_vibes'];
        $expanded_vibes = Intent_Classifier::expand_vibes($vibes);
        
        // Search for each vibe
        $all_results = [];
        $all_zpids = [];
        
        foreach ($expanded_vibes as $vibe) {
            $vibe_params = array_merge($params, ['vibe' => $vibe]);
            
            if (!empty($classification['cuisine'])) {
                $vibe_params['cuisine'] = $classification['cuisine'];
            }
            
            $response = $this->api_client->search_restaurants($vibe_params);
            
            if (!is_wp_error($response) && !empty($response['data'])) {
                foreach ($response['data'] as $restaurant) {
                    $zpid = $restaurant['zpid'];
                    
                    if (!isset($all_zpids[$zpid])) {
                        $all_zpids[$zpid] = true;
                        $restaurant['matched_vibes'] = [$vibe];
                        $restaurant['vibe_score'] = 1.0;
                        $all_results[] = $restaurant;
                    } else {
                        // Restaurant matches multiple vibes - boost score
                        foreach ($all_results as &$existing) {
                            if ($existing['zpid'] === $zpid) {
                                $existing['matched_vibes'][] = $vibe;
                                $existing['vibe_score'] += 0.5;
                                break;
                            }
                        }
                    }
                }
            }
        }
        
        // Sort by vibe score and rating
        usort($all_results, function($a, $b) {
            $score_diff = $b['vibe_score'] - $a['vibe_score'];
            if (abs($score_diff) > 0.1) {
                return $score_diff > 0 ? 1 : -1;
            }
            
            // Secondary sort by rating
            $rating_a = $a['rating'] ?? 0;
            $rating_b = $b['rating'] ?? 0;
            return $rating_b <=> $rating_a;
        });
        
        // Apply limit
        $limited_results = array_slice($all_results, $params['offset'], $params['limit']);
        
        return [
            'results' => $limited_results,
            'total' => count($all_results),
        ];
    }
    
    /**
     * Search by utility
     * 
     * @param array $classification
     * @param array $params
     * @param array $context
     * @return array
     */
    private function search_by_utility($classification, $params, $context) {
        // Add detected features to search
        $features = $classification['detected_features'];
        
        if (!empty($classification['cuisine'])) {
            $params['cuisine'] = $classification['cuisine'];
        }
        
        // Prioritize distance for utility searches
        if (in_array('distance', $features)) {
            $params['radius'] = 2; // Smaller radius for "near me"
        }
        
        // Get results from API
        $response = $this->api_client->search_restaurants($params);
        
        if (is_wp_error($response)) {
            return ['results' => [], 'total' => 0];
        }
        
        $results = $response['data'] ?? [];
        $total = $response['pagination']['total'] ?? count($results);
        
        // Sort by distance for utility searches
        if (!empty($context['location']['lat']) && !empty($context['location']['lng'])) {
            usort($results, function($a, $b) use ($context) {
                $dist_a = $this->calculate_distance(
                    $context['location']['lat'],
                    $context['location']['lng'],
                    $a['latitude'] ?? 0,
                    $a['longitude'] ?? 0
                );
                
                $dist_b = $this->calculate_distance(
                    $context['location']['lat'],
                    $context['location']['lng'],
                    $b['latitude'] ?? 0,
                    $b['longitude'] ?? 0
                );
                
                return $dist_a <=> $dist_b;
            });
        }
        
        return [
            'results' => $results,
            'total' => $total,
        ];
    }
    
    /**
     * Search hybrid (both vibe and utility)
     * 
     * @param array $classification
     * @param array $params
     * @param array $context
     * @return array
     */
    private function search_hybrid($classification, $params, $context) {
        // Get both vibe and utility results
        $vibe_results = $this->search_by_vibe($classification, $params);
        $utility_results = $this->search_by_utility($classification, $params, $context);
        
        // Merge and deduplicate
        $merged = [];
        $seen_zpids = [];
        
        // Interleave results
        $max_count = max(count($vibe_results['results']), count($utility_results['results']));
        
        for ($i = 0; $i < $max_count; $i++) {
            // Add vibe result
            if (isset($vibe_results['results'][$i])) {
                $restaurant = $vibe_results['results'][$i];
                if (!isset($seen_zpids[$restaurant['zpid']])) {
                    $seen_zpids[$restaurant['zpid']] = true;
                    $restaurant['match_type'] = 'vibe';
                    $merged[] = $restaurant;
                }
            }
            
            // Add utility result
            if (isset($utility_results['results'][$i])) {
                $restaurant = $utility_results['results'][$i];
                if (!isset($seen_zpids[$restaurant['zpid']])) {
                    $seen_zpids[$restaurant['zpid']] = true;
                    $restaurant['match_type'] = 'utility';
                    $merged[] = $restaurant;
                }
            }
        }
        
        return [
            'results' => array_slice($merged, 0, $params['limit']),
            'total' => count($merged),
        ];
    }
    
    /**
     * General search fallback
     * 
     * @param string $query
     * @param array $params
     * @param array $context
     * @return array
     */
    private function search_general($query, $params, $context) {
        // Simple keyword search
        $response = $this->api_client->search_restaurants($params);
        
        if (is_wp_error($response)) {
            return ['results' => [], 'total' => 0];
        }
        
        return [
            'results' => $response['data'] ?? [],
            'total' => $response['pagination']['total'] ?? 0,
        ];
    }
    
    /**
     * Process search results
     * 
     * @param array $results
     * @param array $classification
     * @param array $context
     * @return array
     */
    private function process_results($results, $classification, $context) {
        $processed = [];
        
        foreach ($results['results'] as $restaurant) {
            // Check if business exists in WordPress
            $business_post = Business_CPT::get_by_zpid($restaurant['zpid']);
            
            if ($business_post) {
                // Business exists - add WordPress data
                $processed_restaurant = $this->format_existing_business($restaurant, $business_post, $classification);
            } else {
                // Business doesn't exist - format as "Coming Soon"
                $processed_restaurant = $this->format_coming_soon_business($restaurant, $classification);
                
                // Track demand
                Demand_Tracker::track_demand(
                    $restaurant['zpid'],
                    $classification['normalized_query'],
                    $context['location']['city'] ?? null,
                    $context['user_id'],
                    $context['session_id']
                );
            }
            
            $processed[] = $processed_restaurant;
        }
        
        return [
            'results' => $processed,
            'total' => $results['total'],
        ];
    }
    
    /**
     * Format existing business
     * 
     * @param array $restaurant API data
     * @param \WP_Post $post WordPress post
     * @param array $classification
     * @return array
     */
    private function format_existing_business($restaurant, $post, $classification) {
        $formatted = [
            'zpid' => $restaurant['zpid'],
            'name' => $restaurant['name'],
            'wp_post_id' => $post->ID,
            'permalink' => get_permalink($post->ID),
            'status' => 'active',
            'distance_miles' => $restaurant['distance_miles'] ?? null,
            'rating' => $restaurant['rating'] ?? null,
            'review_count' => $restaurant['review_count'] ?? 0,
            'price_range' => $restaurant['price_range'] ?? null,
            'cuisine_type' => $restaurant['cuisine_type'] ?? '',
            'quick_info' => [
                'address' => get_post_meta($post->ID, '_address', true),
                'phone' => get_post_meta($post->ID, '_phone', true),
                'hours_today' => $this->get_hours_today($restaurant),
            ],
        ];
        
        // Add vibe match info for vibe searches
        if ($classification['intent'] === Intent_Classifier::INTENT_VIBE && !empty($restaurant['matched_vibes'])) {
            $formatted['vibe_match'] = [
                'score' => $restaurant['vibe_score'] ?? 0,
                'matched_vibes' => $restaurant['matched_vibes'],
                'explanation' => $this->generate_vibe_explanation($restaurant, $classification),
            ];
        }
        
        // Add featured image if available
        if (has_post_thumbnail($post->ID)) {
            $formatted['image'] = get_the_post_thumbnail_url($post->ID, 'medium');
        }
        
        return $formatted;
    }
    
    /**
     * Format coming soon business
     * 
     * @param array $restaurant API data
     * @param array $classification
     * @return array
     */
    private function format_coming_soon_business($restaurant, $classification) {
        $formatted = [
            'zpid' => $restaurant['zpid'],
            'name' => $restaurant['name'],
            'wp_post_id' => null,
            'permalink' => null,
            'status' => 'coming_soon',
            'distance_miles' => $restaurant['distance_miles'] ?? null,
            'rating' => $restaurant['rating'] ?? null,
            'review_count' => $restaurant['review_count'] ?? 0,
            'price_range' => $restaurant['price_range'] ?? null,
            'cuisine_type' => $restaurant['cuisine_type'] ?? '',
            'quick_info' => [
                'address' => $restaurant['address'] ?? '',
                'phone' => $restaurant['phone'] ?? '',
                'hours_today' => $this->get_hours_today($restaurant),
            ],
            'coming_soon_data' => [
                'notify_url' => rest_url('zippicks/v1/search/notify/' . $restaurant['zpid']),
                'demand_count' => 1, // Will be updated from demand tracker
            ],
        ];
        
        // Add vibe match info for vibe searches
        if ($classification['intent'] === Intent_Classifier::INTENT_VIBE && !empty($restaurant['matched_vibes'])) {
            $formatted['vibe_match'] = [
                'score' => $restaurant['vibe_score'] ?? 0,
                'matched_vibes' => $restaurant['matched_vibes'],
                'explanation' => $this->generate_vibe_explanation($restaurant, $classification),
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Generate vibe explanation
     * 
     * @param array $restaurant
     * @param array $classification
     * @return string
     */
    private function generate_vibe_explanation($restaurant, $classification) {
        $vibes = $restaurant['matched_vibes'] ?? [];
        
        if (empty($vibes)) {
            return '';
        }
        
        // Simple explanation generation - can be enhanced with AI
        $vibe_descriptions = [
            'romantic' => 'intimate atmosphere perfect for dates',
            'cozy' => 'warm and inviting ambiance',
            'trendy' => 'stylish spot with modern vibes',
            'family-friendly' => 'welcoming for all ages',
            'upscale' => 'elegant dining experience',
            'casual' => 'relaxed and comfortable setting',
        ];
        
        $descriptions = [];
        foreach ($vibes as $vibe) {
            if (isset($vibe_descriptions[$vibe])) {
                $descriptions[] = $vibe_descriptions[$vibe];
            }
        }
        
        if (empty($descriptions)) {
            return 'Matches your search preferences';
        }
        
        return 'Features ' . implode(' and ', $descriptions);
    }
    
    /**
     * Get refinement suggestions
     * 
     * @param array $classification
     * @param array $results
     * @return array
     */
    private function get_refinements($classification, $results) {
        $refinements = [];
        
        // Vibe refinements
        if ($classification['intent'] === Intent_Classifier::INTENT_VIBE) {
            $related_vibes = $this->get_related_vibes($classification['detected_vibes']);
            if (!empty($related_vibes)) {
                $refinements['vibes'] = $related_vibes;
            }
        }
        
        // Location refinements
        $nearby_areas = $this->get_nearby_areas($results);
        if (!empty($nearby_areas)) {
            $refinements['nearby_areas'] = $nearby_areas;
        }
        
        // Cuisine refinements
        $cuisines = $this->get_popular_cuisines($results);
        if (!empty($cuisines)) {
            $refinements['cuisines'] = $cuisines;
        }
        
        return $refinements;
    }
    
    /**
     * Track search analytics
     * 
     * @param string $query
     * @param array $classification
     * @param array $results
     * @param array $context
     * @param float $search_time
     */
    private function track_search($query, $classification, $results, $context, $search_time) {
        $this->analytics->track_search([
            'query' => $query,
            'normalized_query' => $classification['normalized_query'],
            'intent_type' => $classification['intent'],
            'confidence_score' => $classification['confidence'],
            'user_id' => $context['user_id'],
            'session_id' => $context['session_id'],
            'location_lat' => $context['location']['lat'] ?? null,
            'location_lng' => $context['location']['lng'] ?? null,
            'location_source' => $context['location']['source'] ?? null,
            'results_count' => count($results['results']),
            'vibes_detected' => json_encode($classification['detected_vibes']),
            'search_time_ms' => round($search_time * 1000),
        ]);
    }
    
    /**
     * Get user location
     * 
     * @param array $params
     * @return array
     */
    private function get_user_location($params) {
        // Use provided location if available
        if (!empty($params['lat']) && !empty($params['lng'])) {
            return [
                'lat' => $params['lat'],
                'lng' => $params['lng'],
                'source' => 'provided',
            ];
        }
        
        // Use Geo Service
        if (class_exists('\ZipPicks\Geo\Location_Detector')) {
            $detector = new \ZipPicks\Geo\Location_Detector();
            $location = $detector->get_user_location();
            
            return [
                'lat' => $location['latitude'],
                'lng' => $location['longitude'],
                'city' => $location['city'] ?? null,
                'state' => $location['state'] ?? null,
                'source' => $location['source'] ?? 'unknown',
            ];
        }
        
        // Default location
        return [
            'lat' => 34.0522,
            'lng' => -118.2437,
            'city' => 'Los Angeles',
            'state' => 'CA',
            'source' => 'default',
        ];
    }
    
    /**
     * Get session ID
     * 
     * @return string
     */
    private function get_session_id() {
        if (class_exists('\ZipPicks\Geo\Location_Detector')) {
            $detector = new \ZipPicks\Geo\Location_Detector();
            return $detector->get_session_id();
        }
        
        // WordPress-safe session management using cookies
        $cookie_name = 'zippicks_session_id';
        $session_id = isset($_COOKIE[$cookie_name]) ? sanitize_text_field($_COOKIE[$cookie_name]) : '';
        
        // Validate existing session ID format
        if (!empty($session_id) && preg_match('/^[a-zA-Z0-9]{32}$/', $session_id)) {
            return $session_id;
        }
        
        // Generate new session ID
        $session_id = wp_generate_password(32, false, false);
        
        // Set cookie with secure parameters
        $expire = time() + (86400 * 30); // 30 days
        $secure = is_ssl();
        $httponly = true;
        
        // Use WordPress cookie path and domain
        $cookie_path = COOKIEPATH ?: '/';
        $cookie_domain = COOKIE_DOMAIN ?: '';
        
        // Set the cookie - will be sent on next page load
        setcookie(
            $cookie_name,
            $session_id,
            $expire,
            $cookie_path,
            $cookie_domain,
            $secure,
            $httponly
        );
        
        // Also store in a transient for immediate use
        set_transient('zps_session_' . $session_id, true, 86400 * 30);
        
        return $session_id;
    }
    
    /**
     * Calculate distance between two points
     * 
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return float Distance in miles
     */
    private function calculate_distance($lat1, $lng1, $lat2, $lng2) {
        $earth_radius = 3959; // miles
        
        $lat_diff = deg2rad($lat2 - $lat1);
        $lng_diff = deg2rad($lng2 - $lng1);
        
        $a = sin($lat_diff / 2) * sin($lat_diff / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lng_diff / 2) * sin($lng_diff / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earth_radius * $c;
    }
    
    /**
     * Get hours for today
     * 
     * @param array $restaurant
     * @return string
     */
    private function get_hours_today($restaurant) {
        if (empty($restaurant['hours'])) {
            return 'Hours not available';
        }
        
        $day = strtolower(date('l'));
        $hours = $restaurant['hours'];
        
        if (is_string($hours)) {
            $hours = json_decode($hours, true);
        }
        
        if (isset($hours[$day])) {
            return $hours[$day];
        }
        
        return 'Closed today';
    }
    
    /**
     * Get user preferences
     * 
     * @param int $user_id
     * @return array
     */
    private function get_user_preferences($user_id) {
        // This would integrate with the Taste Graph
        // For now, return empty array
        return [];
    }
    
    /**
     * Generate cache key
     * 
     * @param string $query
     * @param array $params
     * @param array $classification
     * @return string
     */
    private function generate_cache_key($query, $params, $classification) {
        $key_parts = [
            'search',
            md5($query),
            $classification['intent'],
            $params['lat'] ?? '',
            $params['lng'] ?? '',
            $params['radius'] ?? 10,
            $params['limit'] ?? 20,
            $params['offset'] ?? 0,
        ];
        
        return implode(':', array_filter($key_parts));
    }
    
    /**
     * Get related vibes
     * 
     * @param array $vibes
     * @return array
     */
    private function get_related_vibes($vibes) {
        // This would come from vibe relationships in the database
        // For now, return some hardcoded related vibes
        $related = [];
        
        if (in_array('romantic', $vibes)) {
            $related = array_merge($related, ['intimate', 'upscale', 'quiet']);
        }
        
        if (in_array('casual', $vibes)) {
            $related = array_merge($related, ['relaxed', 'family-friendly', 'comfortable']);
        }
        
        return array_unique(array_diff($related, $vibes));
    }
    
    /**
     * Get nearby areas from results
     * 
     * @param array $results
     * @return array
     */
    private function get_nearby_areas($results) {
        $areas = [];
        
        foreach ($results as $restaurant) {
            if (!empty($restaurant['quick_info']['address'])) {
                // Extract neighborhood from address
                // This is simplified - would need better parsing
                $parts = explode(',', $restaurant['quick_info']['address']);
                if (count($parts) > 1) {
                    $areas[] = trim($parts[count($parts) - 2]);
                }
            }
        }
        
        return array_slice(array_unique($areas), 0, 5);
    }
    
    /**
     * Get popular cuisines from results
     * 
     * @param array $results
     * @return array
     */
    private function get_popular_cuisines($results) {
        $cuisines = [];
        
        foreach ($results as $restaurant) {
            if (!empty($restaurant['cuisine_type'])) {
                $cuisines[] = $restaurant['cuisine_type'];
            }
        }
        
        $cuisine_counts = array_count_values($cuisines);
        arsort($cuisine_counts);
        
        return array_slice(array_keys($cuisine_counts), 0, 5);
    }
}