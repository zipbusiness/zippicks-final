<?php
/**
 * Paid API Manager - Selective enhancement with commercial APIs
 * 
 * @package ZipPicks_Master_Critic
 * @subpackage Hybrid
 */

namespace ZipPicks\MasterCritic\Hybrid;

use WP_Error;

class PaidAPIManager {
    
    /**
     * API configuration
     */
    private const GOOGLE_PLACES_API = 'https://maps.googleapis.com/maps/api/place';
    private const YELP_FUSION_API = 'https://api.yelp.com/v3';
    
    /**
     * Monthly limits (free tier)
     */
    private const GOOGLE_MONTHLY_CREDIT = 200; // $200 free credit
    private const GOOGLE_PLACES_COST = 0.017; // $17 per 1000 requests
    private const YELP_DAILY_LIMIT = 5000;
    
    /**
     * API Keys (stored securely)
     */
    private string $google_api_key;
    private string $yelp_api_key;
    
    /**
     * Usage tracking
     */
    private array $usage_stats = [];
    
    public function __construct() {
        $this->google_api_key = $this->get_google_api_key();
        $this->yelp_api_key = $this->get_yelp_api_key();
        
        // Load usage stats
        $this->usage_stats = get_option('zippicks_api_usage_stats', [
            'google' => ['month' => date('Y-m'), 'count' => 0, 'cost' => 0],
            'yelp' => ['date' => date('Y-m-d'), 'count' => 0]
        ]);
    }
    
    /**
     * Fetch Google Place details for critical data
     */
    public function fetch_google_place_details( string $place_id = null, array $query = [] ): array {
        if (!$this->google_api_key) {
            return ['error' => 'Google API key not configured'];
        }
        
        // Check budget
        if (!$this->has_google_budget()) {
            return ['error' => 'Google Places API budget exhausted'];
        }
        
        // If no place_id, search first
        if (!$place_id && !empty($query['business_name']) && !empty($query['city'])) {
            $search_result = $this->google_place_search($query);
            if (!empty($search_result['place_id'])) {
                $place_id = $search_result['place_id'];
            } else {
                return ['error' => 'Place not found'];
            }
        }
        
        if (!$place_id) {
            return ['error' => 'No place_id provided'];
        }
        
        // Fetch place details
        $fields = [
            'name',
            'formatted_address',
            'formatted_phone_number',
            'opening_hours',
            'website',
            'rating',
            'user_ratings_total',
            'price_level',
            'types',
            'business_status',
            'geometry',
            'photos',
            'reviews'
        ];
        
        $params = [
            'place_id' => $place_id,
            'fields' => implode(',', $fields),
            'key' => $this->google_api_key
        ];
        
        $url = self::GOOGLE_PLACES_API . '/details/json?' . http_build_query($params);
        
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Referer' => home_url()
            ]
        ]);
        
        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($data['status'] !== 'OK') {
            return ['error' => 'Google API error: ' . $data['status']];
        }
        
        // Track usage
        $this->track_google_usage();
        
        // Transform to our format
        $result = $data['result'];
        
        return [
            'source' => 'google_places',
            'place_id' => $place_id,
            'name' => $result['name'] ?? '',
            'address' => $result['formatted_address'] ?? '',
            'phone' => $result['formatted_phone_number'] ?? '',
            'website' => $result['website'] ?? '',
            'business_status' => $result['business_status'] ?? 'OPERATIONAL',
            'hours' => $this->parse_google_hours($result['opening_hours'] ?? []),
            'rating' => $result['rating'] ?? 0,
            'review_count' => $result['user_ratings_total'] ?? 0,
            'price_level' => $result['price_level'] ?? 0,
            'types' => $result['types'] ?? [],
            'coordinates' => [
                'lat' => $result['geometry']['location']['lat'] ?? 0,
                'lon' => $result['geometry']['location']['lng'] ?? 0
            ],
            'photos' => $this->parse_google_photos($result['photos'] ?? []),
            'reviews' => $this->parse_google_reviews($result['reviews'] ?? []),
            'is_operational' => ($result['business_status'] ?? '') === 'OPERATIONAL',
            'fetched_at' => time()
        ];
    }
    
    /**
     * Fetch Yelp reviews and detailed data
     */
    public function fetch_yelp_reviews( string $business_id = null, array $query = [] ): array {
        if (!$this->yelp_api_key) {
            return ['error' => 'Yelp API key not configured'];
        }
        
        // Check daily limit
        if (!$this->has_yelp_quota()) {
            return ['error' => 'Yelp API daily limit reached'];
        }
        
        // If no business_id, search first
        if (!$business_id && !empty($query['business_name']) && !empty($query['city'])) {
            $search_result = $this->yelp_business_search($query);
            if (!empty($search_result['id'])) {
                $business_id = $search_result['id'];
            } else {
                return ['error' => 'Business not found on Yelp'];
            }
        }
        
        if (!$business_id) {
            return ['error' => 'No business_id provided'];
        }
        
        // Fetch business details
        $url = self::YELP_FUSION_API . '/businesses/' . $business_id;
        
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->yelp_api_key,
                'Accept' => 'application/json'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($data['error'])) {
            return ['error' => 'Yelp API error: ' . $data['error']['description']];
        }
        
        // Track usage
        $this->track_yelp_usage();
        
        // Fetch reviews separately
        $reviews = $this->fetch_yelp_business_reviews($business_id);
        
        return [
            'source' => 'yelp',
            'id' => $data['id'] ?? '',
            'name' => $data['name'] ?? '',
            'address' => $this->format_yelp_address($data['location'] ?? []),
            'phone' => $data['display_phone'] ?? '',
            'url' => $data['url'] ?? '',
            'image_url' => $data['image_url'] ?? '',
            'is_closed' => $data['is_closed'] ?? false,
            'rating' => $data['rating'] ?? 0,
            'review_count' => $data['review_count'] ?? 0,
            'categories' => $this->parse_yelp_categories($data['categories'] ?? []),
            'price' => $data['price'] ?? '',
            'hours' => $this->parse_yelp_hours($data['hours'] ?? []),
            'coordinates' => [
                'lat' => $data['coordinates']['latitude'] ?? 0,
                'lon' => $data['coordinates']['longitude'] ?? 0
            ],
            'photos' => $data['photos'] ?? [],
            'reviews' => $reviews,
            'transactions' => $data['transactions'] ?? [],
            'special_hours' => $data['special_hours'] ?? [],
            'fetched_at' => time()
        ];
    }
    
    /**
     * Google Place search
     */
    private function google_place_search( array $query ): array {
        $search_query = $query['business_name'];
        if (!empty($query['city'])) {
            $search_query .= ' ' . $query['city'];
        }
        if (!empty($query['state'])) {
            $search_query .= ' ' . $query['state'];
        }
        
        $params = [
            'input' => $search_query,
            'inputtype' => 'textquery',
            'fields' => 'place_id,name,formatted_address,types',
            'key' => $this->google_api_key
        ];
        
        $url = self::GOOGLE_PLACES_API . '/findplacefromtext/json?' . http_build_query($params);
        
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Referer' => home_url()
            ]
        ]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($data['status'] === 'OK' && !empty($data['candidates'][0])) {
            $this->track_google_usage();
            return $data['candidates'][0];
        }
        
        return [];
    }
    
    /**
     * Yelp business search
     */
    private function yelp_business_search( array $query ): array {
        $params = [
            'term' => $query['business_name'],
            'location' => $query['city'] . ', ' . ($query['state'] ?? ''),
            'limit' => 5,
            'sort_by' => 'best_match'
        ];
        
        $url = self::YELP_FUSION_API . '/businesses/search?' . http_build_query($params);
        
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->yelp_api_key,
                'Accept' => 'application/json'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($data['businesses'][0])) {
            $this->track_yelp_usage();
            
            // Find best match
            $best_match = null;
            $best_score = 0;
            
            foreach ($data['businesses'] as $business) {
                similar_text(
                    strtolower($query['business_name']),
                    strtolower($business['name']),
                    $percent
                );
                
                if ($percent > $best_score) {
                    $best_score = $percent;
                    $best_match = $business;
                }
            }
            
            return $best_match ?: $data['businesses'][0];
        }
        
        return [];
    }
    
    /**
     * Fetch Google Photos with references
     */
    public function fetch_google_photos( string $place_id ): array {
        // This would be called separately if photos are specifically needed
        // Implementation would fetch photo references and generate URLs
        return [];
    }
    
    /**
     * Fetch Yelp business reviews
     */
    private function fetch_yelp_business_reviews( string $business_id ): array {
        $url = self::YELP_FUSION_API . '/businesses/' . $business_id . '/reviews';
        
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->yelp_api_key,
                'Accept' => 'application/json'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($data['reviews'])) {
            $this->track_yelp_usage();
            
            return array_map(function($review) {
                return [
                    'id' => $review['id'],
                    'rating' => $review['rating'],
                    'text' => $review['text'],
                    'time_created' => $review['time_created'],
                    'user' => [
                        'name' => $review['user']['name'] ?? '',
                        'image_url' => $review['user']['image_url'] ?? ''
                    ]
                ];
            }, $data['reviews']);
        }
        
        return [];
    }
    
    /**
     * Check Google budget remaining
     */
    private function has_google_budget(): bool {
        $current_month = date('Y-m');
        
        if ($this->usage_stats['google']['month'] !== $current_month) {
            // Reset for new month
            $this->usage_stats['google'] = [
                'month' => $current_month,
                'count' => 0,
                'cost' => 0
            ];
        }
        
        // Check if we're within free tier
        return $this->usage_stats['google']['cost'] < self::GOOGLE_MONTHLY_CREDIT;
    }
    
    /**
     * Check Yelp daily quota
     */
    private function has_yelp_quota(): bool {
        $today = date('Y-m-d');
        
        if ($this->usage_stats['yelp']['date'] !== $today) {
            // Reset for new day
            $this->usage_stats['yelp'] = [
                'date' => $today,
                'count' => 0
            ];
        }
        
        return $this->usage_stats['yelp']['count'] < self::YELP_DAILY_LIMIT;
    }
    
    /**
     * Track Google API usage
     */
    private function track_google_usage(): void {
        $this->usage_stats['google']['count']++;
        $this->usage_stats['google']['cost'] += self::GOOGLE_PLACES_COST;
        
        update_option('zippicks_api_usage_stats', $this->usage_stats);
        
        // Log usage
        $this->log_api_usage('google_places', [
            'count' => $this->usage_stats['google']['count'],
            'cost' => $this->usage_stats['google']['cost']
        ]);
    }
    
    /**
     * Track Yelp API usage
     */
    private function track_yelp_usage(): void {
        $this->usage_stats['yelp']['count']++;
        
        update_option('zippicks_api_usage_stats', $this->usage_stats);
        
        // Log usage
        $this->log_api_usage('yelp', [
            'count' => $this->usage_stats['yelp']['count'],
            'remaining' => self::YELP_DAILY_LIMIT - $this->usage_stats['yelp']['count']
        ]);
    }
    
    /**
     * Parse Google opening hours
     */
    private function parse_google_hours( array $hours_data ): array {
        if (empty($hours_data['weekday_text'])) {
            return [];
        }
        
        $parsed_hours = [];
        
        foreach ($hours_data['weekday_text'] as $day_hours) {
            // Format: "Monday: 11:00 AM – 10:00 PM"
            $parts = explode(': ', $day_hours, 2);
            if (count($parts) === 2) {
                $day = strtolower($parts[0]);
                $hours = $parts[1];
                $parsed_hours[$day] = $hours;
            }
        }
        
        return [
            'formatted' => $parsed_hours,
            'is_open_now' => $hours_data['open_now'] ?? false,
            'periods' => $hours_data['periods'] ?? []
        ];
    }
    
    /**
     * Parse Google photos
     */
    private function parse_google_photos( array $photos ): array {
        return array_map(function($photo) {
            return [
                'reference' => $photo['photo_reference'] ?? '',
                'height' => $photo['height'] ?? 0,
                'width' => $photo['width'] ?? 0,
                'attributions' => $photo['html_attributions'] ?? []
            ];
        }, array_slice($photos, 0, 5)); // Limit to 5 photos
    }
    
    /**
     * Parse Google reviews
     */
    private function parse_google_reviews( array $reviews ): array {
        return array_map(function($review) {
            return [
                'author_name' => $review['author_name'] ?? '',
                'rating' => $review['rating'] ?? 0,
                'text' => $review['text'] ?? '',
                'time' => $review['time'] ?? 0,
                'relative_time' => $review['relative_time_description'] ?? ''
            ];
        }, array_slice($reviews, 0, 5)); // Limit to 5 reviews
    }
    
    /**
     * Format Yelp address
     */
    private function format_yelp_address( array $location ): string {
        $parts = array_filter([
            $location['address1'] ?? '',
            $location['address2'] ?? '',
            $location['city'] ?? '',
            $location['state'] ?? '',
            $location['zip_code'] ?? ''
        ]);
        
        return implode(', ', $parts);
    }
    
    /**
     * Parse Yelp categories
     */
    private function parse_yelp_categories( array $categories ): array {
        return array_map(function($category) {
            return [
                'alias' => $category['alias'] ?? '',
                'title' => $category['title'] ?? ''
            ];
        }, $categories);
    }
    
    /**
     * Parse Yelp hours
     */
    private function parse_yelp_hours( array $hours_data ): array {
        if (empty($hours_data[0]['open'])) {
            return [];
        }
        
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $parsed_hours = [];
        $is_open_now = $hours_data[0]['is_open_now'] ?? false;
        
        foreach ($hours_data[0]['open'] as $period) {
            $day_index = $period['day'] ?? 0;
            $day = $days[$day_index] ?? 'Unknown';
            
            $start = $this->format_time($period['start'] ?? '');
            $end = $this->format_time($period['end'] ?? '');
            
            if (!isset($parsed_hours[$day])) {
                $parsed_hours[$day] = [];
            }
            
            $parsed_hours[$day][] = $start . ' - ' . $end;
        }
        
        // Combine multiple periods per day
        foreach ($parsed_hours as $day => $periods) {
            $parsed_hours[$day] = implode(', ', $periods);
        }
        
        return [
            'formatted' => $parsed_hours,
            'is_open_now' => $is_open_now,
            'raw' => $hours_data[0]['open'] ?? []
        ];
    }
    
    /**
     * Format time from HHMM to readable format
     */
    private function format_time( string $time ): string {
        if (strlen($time) !== 4) {
            return $time;
        }
        
        $hour = (int)substr($time, 0, 2);
        $minute = substr($time, 2, 2);
        
        $period = $hour >= 12 ? 'PM' : 'AM';
        $hour = $hour > 12 ? $hour - 12 : ($hour === 0 ? 12 : $hour);
        
        return $hour . ':' . $minute . ' ' . $period;
    }
    
    /**
     * Get Google API key from settings
     */
    private function get_google_api_key(): string {
        // First check environment variable
        if (defined('ZIPPICKS_GOOGLE_API_KEY')) {
            return ZIPPICKS_GOOGLE_API_KEY;
        }
        
        // Check encrypted option (as saved by settings page)
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-security.php';
        $encrypted_key = \ZipPicks_Master_Critic_Security::get_encrypted_option('zippicks_google_api_key', '');
        if (!empty($encrypted_key)) {
            return $encrypted_key;
        }
        
        // Fallback to plain option for backward compatibility
        return get_option('zippicks_google_api_key', '');
    }
    
    /**
     * Get Yelp API key from settings
     */
    private function get_yelp_api_key(): string {
        // First check environment variable
        if (defined('ZIPPICKS_YELP_API_KEY')) {
            return ZIPPICKS_YELP_API_KEY;
        }
        
        // Check encrypted option (as saved by settings page)
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-security.php';
        $encrypted_key = \ZipPicks_Master_Critic_Security::get_encrypted_option('zippicks_yelp_api_key', '');
        if (!empty($encrypted_key)) {
            return $encrypted_key;
        }
        
        // Fallback to plain option for backward compatibility
        return get_option('zippicks_yelp_api_key', '');
    }
    
    /**
     * Log API usage for monitoring
     */
    private function log_api_usage( string $api, array $details ): void {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'zippicks_api_usage_log',
            [
                'api_name' => $api,
                'usage_date' => current_time('mysql'),
                'request_count' => $details['count'] ?? 1,
                'cost' => $details['cost'] ?? 0,
                'metadata' => json_encode($details)
            ]
        );
    }
    
    /**
     * Get current usage statistics
     */
    public function get_usage_stats(): array {
        return [
            'google' => [
                'month' => $this->usage_stats['google']['month'],
                'requests' => $this->usage_stats['google']['count'],
                'cost' => round($this->usage_stats['google']['cost'], 2),
                'remaining_credit' => round(self::GOOGLE_MONTHLY_CREDIT - $this->usage_stats['google']['cost'], 2),
                'percentage_used' => round(($this->usage_stats['google']['cost'] / self::GOOGLE_MONTHLY_CREDIT) * 100, 1)
            ],
            'yelp' => [
                'date' => $this->usage_stats['yelp']['date'],
                'requests' => $this->usage_stats['yelp']['count'],
                'remaining' => self::YELP_DAILY_LIMIT - $this->usage_stats['yelp']['count'],
                'percentage_used' => round(($this->usage_stats['yelp']['count'] / self::YELP_DAILY_LIMIT) * 100, 1)
            ]
        ];
    }
    
    /**
     * Reset usage statistics (for testing)
     */
    public function reset_usage_stats(): void {
        $this->usage_stats = [
            'google' => ['month' => date('Y-m'), 'count' => 0, 'cost' => 0],
            'yelp' => ['date' => date('Y-m-d'), 'count' => 0]
        ];
        
        update_option('zippicks_api_usage_stats', $this->usage_stats);
    }
}