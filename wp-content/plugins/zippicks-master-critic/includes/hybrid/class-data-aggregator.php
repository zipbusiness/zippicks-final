<?php
/**
 * Data Aggregator - Fetches data from free sources
 * 
 * @package ZipPicks_Master_Critic
 * @subpackage Hybrid
 */

namespace ZipPicks\MasterCritic\Hybrid;

use WP_Error;

class DataAggregator {
    
    /**
     * API endpoints for free data sources
     */
    private const OSM_NOMINATIM_API = 'https://nominatim.openstreetmap.org/';
    private const OSM_OVERPASS_API = 'https://overpass-api.de/api/interpreter';
    private const WIKIDATA_API = 'https://www.wikidata.org/w/api.php';
    
    /**
     * Request timeout
     */
    private const REQUEST_TIMEOUT = 10;
    
    /**
     * User agent for API requests
     */
    private const USER_AGENT = 'ZipPicks/1.0 (https://zippicks.com; contact@zippicks.com)';
    
    /**
     * Rate limiting delays (milliseconds)
     */
    private const RATE_LIMITS = [
        'osm' => 1000,      // 1 request per second
        'wikidata' => 500,  // 2 requests per second
        'gov' => 100        // 10 requests per second (most allow higher)
    ];
    
    private $last_request_time = [];
    
    /**
     * Fetch OpenStreetMap data
     */
    public function fetch_osm_data( array $query ): array {
        $this->rate_limit('osm');
        
        $osm_data = [
            'source' => 'openstreetmap',
            'fetched_at' => time()
        ];
        
        // First, try to find by name and location
        if (!empty($query['business_name']) && !empty($query['city'])) {
            $search_results = $this->osm_search($query['business_name'], $query['city'], $query['state'] ?? '');
            
            if (!empty($search_results)) {
                $best_match = $this->find_best_osm_match($search_results, $query);
                
                if ($best_match) {
                    $osm_data = array_merge($osm_data, $best_match);
                    
                    // Get detailed data from Overpass API
                    if (!empty($best_match['osm_id']) && !empty($best_match['osm_type'])) {
                        $details = $this->osm_get_details($best_match['osm_id'], $best_match['osm_type']);
                        $osm_data = array_merge($osm_data, $details);
                    }
                }
            }
        }
        
        // Try by coordinates if available
        if (empty($osm_data['id']) && !empty($query['lat']) && !empty($query['lon'])) {
            $nearby = $this->osm_nearby_search($query['lat'], $query['lon'], 50); // 50m radius
            if (!empty($nearby)) {
                $osm_data = array_merge($osm_data, $nearby[0]); // Take closest
            }
        }
        
        return $osm_data;
    }
    
    /**
     * Search OSM by name and location
     */
    private function osm_search( string $name, string $city, string $state = '' ): array {
        $params = [
            'q' => $name . ', ' . $city . ($state ? ', ' . $state : ''),
            'format' => 'json',
            'limit' => 10,
            'addressdetails' => 1,
            'extratags' => 1,
            'namedetails' => 1
        ];
        
        $url = self::OSM_NOMINATIM_API . 'search?' . http_build_query($params);
        
        $response = wp_remote_get($url, [
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => [
                'User-Agent' => self::USER_AGENT
            ]
        ]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        return is_array($data) ? $data : [];
    }
    
    /**
     * Get detailed OSM data via Overpass API
     */
    private function osm_get_details( string $osm_id, string $osm_type ): array {
        $this->rate_limit('osm');
        
        // Build Overpass query
        $type_map = [
            'node' => 'node',
            'way' => 'way',
            'relation' => 'rel'
        ];
        
        $element_type = $type_map[$osm_type] ?? 'node';
        
        $overpass_query = "[out:json][timeout:10];\n";
        $overpass_query .= "{$element_type}({$osm_id});\n";
        $overpass_query .= "out tags;";
        
        $response = wp_remote_post(self::OSM_OVERPASS_API, [
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => [
                'data' => $overpass_query
            ]
        ]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($data['elements'][0]['tags'])) {
            $tags = $data['elements'][0]['tags'];
            
            return [
                'tags' => $tags,
                'name' => $tags['name'] ?? '',
                'opening_hours' => $tags['opening_hours'] ?? '',
                'website' => $tags['website'] ?? $tags['contact:website'] ?? '',
                'phone' => $tags['phone'] ?? $tags['contact:phone'] ?? '',
                'cuisine' => $tags['cuisine'] ?? '',
                'wheelchair' => $tags['wheelchair'] ?? '',
                'outdoor_seating' => $tags['outdoor_seating'] ?? '',
                'takeaway' => $tags['takeaway'] ?? '',
                'delivery' => $tags['delivery'] ?? '',
                'payment_types' => $this->extract_payment_types($tags)
            ];
        }
        
        return [];
    }
    
    /**
     * Fetch Wikidata information
     */
    public function fetch_wikidata( array $query ): array {
        $this->rate_limit('wikidata');
        
        $wikidata = [
            'source' => 'wikidata',
            'fetched_at' => time()
        ];
        
        // Search for entity
        if (!empty($query['business_name'])) {
            $search_params = [
                'action' => 'wbsearchentities',
                'search' => $query['business_name'],
                'language' => 'en',
                'limit' => 5,
                'format' => 'json'
            ];
            
            if (!empty($query['city'])) {
                $search_params['search'] .= ' ' . $query['city'];
            }
            
            $url = self::WIKIDATA_API . '?' . http_build_query($search_params);
            
            $response = wp_remote_get($url, [
                'timeout' => self::REQUEST_TIMEOUT,
                'headers' => [
                    'User-Agent' => self::USER_AGENT
                ]
            ]);
            
            if (!is_wp_error($response)) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                
                if (!empty($data['search'])) {
                    foreach ($data['search'] as $result) {
                        // Check if it's a relevant entity (restaurant, hotel, etc.)
                        if ($this->is_relevant_wikidata_entity($result)) {
                            $entity_data = $this->fetch_wikidata_entity($result['id']);
                            if (!empty($entity_data)) {
                                $wikidata = array_merge($wikidata, $entity_data);
                                break;
                            }
                        }
                    }
                }
            }
        }
        
        return $wikidata;
    }
    
    /**
     * Fetch detailed Wikidata entity
     */
    private function fetch_wikidata_entity( string $entity_id ): array {
        $this->rate_limit('wikidata');
        
        $params = [
            'action' => 'wbgetentities',
            'ids' => $entity_id,
            'languages' => 'en',
            'props' => 'labels|descriptions|claims|sitelinks',
            'format' => 'json'
        ];
        
        $url = self::WIKIDATA_API . '?' . http_build_query($params);
        
        $response = wp_remote_get($url, [
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => [
                'User-Agent' => self::USER_AGENT
            ]
        ]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($data['entities'][$entity_id])) {
            $entity = $data['entities'][$entity_id];
            $claims = $entity['claims'] ?? [];
            
            return [
                'id' => $entity_id,
                'label' => $entity['labels']['en']['value'] ?? '',
                'description' => $entity['descriptions']['en']['value'] ?? '',
                'founded' => $this->extract_wikidata_claim($claims, 'P571'), // inception
                'website' => $this->extract_wikidata_claim($claims, 'P856'), // official website
                'social_media' => [
                    'facebook' => $this->extract_wikidata_claim($claims, 'P2013'),
                    'twitter' => $this->extract_wikidata_claim($claims, 'P2002'),
                    'instagram' => $this->extract_wikidata_claim($claims, 'P2003')
                ],
                'coordinates' => $this->extract_wikidata_coordinates($claims),
                'image' => $this->extract_wikidata_claim($claims, 'P18'), // image
                'instance_of' => $this->extract_wikidata_claim($claims, 'P31'), // instance of
                'wikipedia_link' => $entity['sitelinks']['enwiki']['title'] ?? ''
            ];
        }
        
        return [];
    }
    
    /**
     * Fetch government data (health scores, licenses)
     */
    public function fetch_government_data( array $query ): array {
        $gov_data = [
            'source' => 'government',
            'fetched_at' => time()
        ];
        
        // Determine which government APIs to query based on location
        if (!empty($query['city']) && !empty($query['state'])) {
            $city_apis = $this->get_city_gov_apis($query['city'], $query['state']);
            
            foreach ($city_apis as $api_config) {
                $api_data = $this->query_gov_api($api_config, $query);
                if (!empty($api_data)) {
                    $gov_data = array_merge($gov_data, $api_data);
                }
            }
        }
        
        return $gov_data;
    }
    
    /**
     * Get city-specific government APIs
     */
    private function get_city_gov_apis( string $city, string $state ): array {
        // Map of cities to their open data APIs
        $city_apis = [
            'New York' => [
                'health_inspections' => [
                    'url' => 'https://data.cityofnewyork.us/resource/43nn-pn8j.json',
                    'name_field' => 'dba',
                    'score_field' => 'grade',
                    'date_field' => 'inspection_date'
                ]
            ],
            'San Francisco' => [
                'health_inspections' => [
                    'url' => 'https://data.sfgov.org/resource/pyih-qa8i.json',
                    'name_field' => 'business_name',
                    'score_field' => 'inspection_score',
                    'date_field' => 'inspection_date'
                ]
            ],
            'Chicago' => [
                'food_inspections' => [
                    'url' => 'https://data.cityofchicago.org/resource/4ijn-s7e5.json',
                    'name_field' => 'dba_name',
                    'score_field' => 'results',
                    'date_field' => 'inspection_date'
                ],
                'business_licenses' => [
                    'url' => 'https://data.cityofchicago.org/resource/r5kz-chrr.json',
                    'name_field' => 'doing_business_as_name',
                    'license_field' => 'license_status',
                    'date_field' => 'license_term_start_date'
                ]
            ],
            'Los Angeles' => [
                'health_inspections' => [
                    'url' => 'https://data.lacity.org/resource/zvt2-8uas.json',
                    'name_field' => 'facility_name',
                    'score_field' => 'score',
                    'date_field' => 'activity_date'
                ]
            ]
        ];
        
        return $city_apis[$city] ?? [];
    }
    
    /**
     * Query a government API
     */
    private function query_gov_api( array $api_config, array $query ): array {
        $this->rate_limit('gov');
        
        $params = [
            '$where' => sprintf(
                "UPPER(%s) LIKE '%%%s%%'",
                $api_config['name_field'],
                strtoupper($query['business_name'])
            ),
            '$limit' => 10,
            '$order' => $api_config['date_field'] . ' DESC'
        ];
        
        $url = $api_config['url'] . '?' . http_build_query($params);
        
        $response = wp_remote_get($url, [
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'X-App-Token' => null // Socrata discontinued - removing token support
            ]
        ]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($data) && is_array($data)) {
            // Find best match
            $best_match = $this->find_best_gov_match($data, $query, $api_config);
            
            if ($best_match) {
                $result = [
                    'has_data' => true,
                    'last_updated' => $best_match[$api_config['date_field']] ?? ''
                ];
                
                if (!empty($api_config['score_field'])) {
                    $result['health_score'] = $best_match[$api_config['score_field']] ?? '';
                    $result['health_inspection_date'] = $best_match[$api_config['date_field']] ?? '';
                }
                
                if (!empty($api_config['license_field'])) {
                    $result['business_license'] = $best_match[$api_config['license_field']] ?? '';
                    $result['license_date'] = $best_match[$api_config['date_field']] ?? '';
                }
                
                return $result;
            }
        }
        
        return [];
    }
    
    /**
     * Fetch social signals from various sources
     */
    public function fetch_social_signals( array $query ): array {
        $social_data = [
            'source' => 'social_signals',
            'fetched_at' => time()
        ];
        
        // Instagram hashtag data (via unofficial endpoints or scraping)
        $instagram_data = $this->fetch_instagram_signals($query);
        if (!empty($instagram_data)) {
            $social_data['instagram'] = $instagram_data;
        }
        
        // Twitter/X mentions (if API available)
        $twitter_data = $this->fetch_twitter_signals($query);
        if (!empty($twitter_data)) {
            $social_data['twitter'] = $twitter_data;
        }
        
        // Google Trends data
        $trends_data = $this->fetch_trends_data($query);
        if (!empty($trends_data)) {
            $social_data['trends'] = $trends_data;
        }
        
        // Calculate aggregate metrics
        $social_data['mention_count'] = 
            ($instagram_data['post_count'] ?? 0) + 
            ($twitter_data['mention_count'] ?? 0);
        
        $social_data['trending'] = 
            ($instagram_data['is_trending'] ?? false) || 
            ($trends_data['is_rising'] ?? false);
        
        $social_data['engagement_rate'] = $this->calculate_engagement_rate($social_data);
        
        return $social_data;
    }
    
    /**
     * Fetch community data (Reddit, local forums, etc.)
     */
    public function fetch_community_data( array $query ): array {
        $community_data = [
            'source' => 'community',
            'fetched_at' => time()
        ];
        
        // Reddit mentions via Pushshift or Reddit API
        $reddit_data = $this->fetch_reddit_mentions($query);
        if (!empty($reddit_data)) {
            $community_data['reddit'] = $reddit_data;
        }
        
        // Local news mentions (via news APIs)
        $news_data = $this->fetch_local_news_mentions($query);
        if (!empty($news_data)) {
            $community_data['local_news'] = $news_data;
        }
        
        return $community_data;
    }
    
    /**
     * Rate limiting helper
     */
    private function rate_limit( string $source ): void {
        $limit = self::RATE_LIMITS[$source] ?? 100;
        $last_time = $this->last_request_time[$source] ?? 0;
        
        $elapsed = (microtime(true) * 1000) - $last_time;
        
        if ($elapsed < $limit) {
            usleep(($limit - $elapsed) * 1000);
        }
        
        $this->last_request_time[$source] = microtime(true) * 1000;
    }
    
    /**
     * Find best OSM match from search results
     */
    private function find_best_osm_match( array $results, array $query ): ?array {
        $best_score = 0;
        $best_match = null;
        
        foreach ($results as $result) {
            $score = 0;
            
            // Name similarity
            if (!empty($result['display_name'])) {
                $name_similarity = similar_text(
                    strtolower($query['business_name']), 
                    strtolower($result['display_name']), 
                    $percent
                );
                $score += $percent;
            }
            
            // Location match
            if (!empty($result['address']['city']) && 
                strcasecmp($result['address']['city'], $query['city']) === 0) {
                $score += 30;
            }
            
            // Business type match
            if (!empty($result['type']) && $this->is_relevant_osm_type($result['type'])) {
                $score += 20;
            }
            
            if ($score > $best_score && $score > 50) { // Minimum threshold
                $best_score = $score;
                $best_match = [
                    'id' => $result['place_id'],
                    'osm_id' => $result['osm_id'],
                    'osm_type' => $result['osm_type'],
                    'name' => $result['namedetails']['name'] ?? $result['display_name'],
                    'lat' => $result['lat'],
                    'lon' => $result['lon'],
                    'address' => $result['address'],
                    'type' => $result['type'],
                    'class' => $result['class'],
                    'extratags' => $result['extratags'] ?? []
                ];
            }
        }
        
        return $best_match;
    }
    
    /**
     * Check if OSM type is relevant for businesses
     */
    private function is_relevant_osm_type( string $type ): bool {
        $relevant_types = [
            'restaurant', 'cafe', 'bar', 'pub', 'fast_food',
            'hotel', 'motel', 'guest_house',
            'hairdresser', 'beauty_salon', 'spa',
            'shop', 'store', 'marketplace'
        ];
        
        return in_array($type, $relevant_types, true);
    }
    
    /**
     * Extract payment types from OSM tags
     */
    private function extract_payment_types( array $tags ): array {
        $payment_types = [];
        
        $payment_keys = [
            'payment:cash' => 'cash',
            'payment:credit_cards' => 'credit_cards',
            'payment:debit_cards' => 'debit_cards',
            'payment:mastercard' => 'mastercard',
            'payment:visa' => 'visa',
            'payment:american_express' => 'amex',
            'payment:apple_pay' => 'apple_pay',
            'payment:google_pay' => 'google_pay'
        ];
        
        foreach ($payment_keys as $key => $type) {
            if (!empty($tags[$key]) && $tags[$key] === 'yes') {
                $payment_types[] = $type;
            }
        }
        
        return $payment_types;
    }
    
    /**
     * Check if Wikidata entity is relevant
     */
    private function is_relevant_wikidata_entity( array $entity ): bool {
        // Check description for business-related terms
        $description = strtolower($entity['description'] ?? '');
        
        $business_terms = [
            'restaurant', 'cafe', 'bar', 'hotel', 'motel',
            'salon', 'spa', 'shop', 'store', 'company',
            'chain', 'franchise', 'establishment'
        ];
        
        foreach ($business_terms as $term) {
            if (strpos($description, $term) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract Wikidata claim value
     */
    private function extract_wikidata_claim( array $claims, string $property ): ?string {
        if (empty($claims[$property])) {
            return null;
        }
        
        $claim = $claims[$property][0] ?? null;
        
        if (!$claim) {
            return null;
        }
        
        $mainsnak = $claim['mainsnak'] ?? [];
        $datavalue = $mainsnak['datavalue'] ?? [];
        
        switch ($datavalue['type'] ?? '') {
            case 'string':
                return $datavalue['value'];
            case 'time':
                return $datavalue['value']['time'] ?? null;
            case 'wikibase-entityid':
                return $datavalue['value']['id'] ?? null;
            default:
                return null;
        }
    }
    
    /**
     * Extract coordinates from Wikidata claims
     */
    private function extract_wikidata_coordinates( array $claims ): ?array {
        if (empty($claims['P625'])) { // coordinate location
            return null;
        }
        
        $coord_claim = $claims['P625'][0] ?? null;
        
        if (!$coord_claim) {
            return null;
        }
        
        $value = $coord_claim['mainsnak']['datavalue']['value'] ?? null;
        
        if ($value) {
            return [
                'lat' => $value['latitude'],
                'lon' => $value['longitude']
            ];
        }
        
        return null;
    }
    
    /**
     * Find best government data match
     */
    private function find_best_gov_match( array $results, array $query, array $api_config ): ?array {
        $best_score = 0;
        $best_match = null;
        
        foreach ($results as $result) {
            $score = 0;
            
            // Name similarity
            $name = $result[$api_config['name_field']] ?? '';
            similar_text(
                strtolower($query['business_name']), 
                strtolower($name), 
                $percent
            );
            $score += $percent;
            
            // Address match if available
            if (!empty($result['address']) && !empty($query['address'])) {
                similar_text(
                    strtolower($query['address']), 
                    strtolower($result['address']), 
                    $addr_percent
                );
                $score += $addr_percent * 0.5;
            }
            
            if ($score > $best_score && $score > 70) { // Higher threshold for gov data
                $best_score = $score;
                $best_match = $result;
            }
        }
        
        return $best_match;
    }
    
    
    /**
     * Fetch Instagram signals (placeholder - would need proper API)
     */
    private function fetch_instagram_signals( array $query ): array {
        // In production, this would use Instagram Basic Display API
        // or web scraping with proper rate limiting
        
        return [
            'post_count' => rand(10, 500), // Simulated
            'recent_posts' => rand(1, 20),
            'is_trending' => rand(0, 100) > 80,
            'avg_likes' => rand(50, 1000)
        ];
    }
    
    /**
     * Fetch Twitter signals (placeholder - would need API)
     */
    private function fetch_twitter_signals( array $query ): array {
        // In production, this would use Twitter API v2
        
        return [
            'mention_count' => rand(5, 200), // Simulated
            'recent_mentions' => rand(1, 10),
            'sentiment' => 'positive'
        ];
    }
    
    /**
     * Fetch Google Trends data (placeholder)
     */
    private function fetch_trends_data( array $query ): array {
        // In production, this would use pytrends or similar
        
        return [
            'interest_score' => rand(20, 100),
            'is_rising' => rand(0, 100) > 70
        ];
    }
    
    /**
     * Calculate engagement rate from social data
     */
    private function calculate_engagement_rate( array $social_data ): float {
        $total_posts = ($social_data['instagram']['post_count'] ?? 0) + 
                      ($social_data['twitter']['mention_count'] ?? 0);
        
        $total_engagement = ($social_data['instagram']['avg_likes'] ?? 0) * 
                           ($social_data['instagram']['post_count'] ?? 1);
        
        if ($total_posts > 0) {
            return round($total_engagement / $total_posts / 1000, 2); // Simplified
        }
        
        return 0.0;
    }
    
    /**
     * Fetch Reddit mentions (placeholder)
     */
    private function fetch_reddit_mentions( array $query ): array {
        // In production, would use Reddit API or Pushshift
        
        return [
            'mention_count' => rand(0, 50),
            'recent_posts' => rand(0, 5),
            'subreddits' => ['food', 'cityname']
        ];
    }
    
    /**
     * Fetch local news mentions (placeholder)
     */
    private function fetch_local_news_mentions( array $query ): array {
        // In production, would use news APIs
        
        return [
            'article_count' => rand(0, 10),
            'recent_articles' => rand(0, 3),
            'sentiment' => 'positive'
        ];
    }
    
    /**
     * Search nearby OSM features
     */
    private function osm_nearby_search( float $lat, float $lon, int $radius ): array {
        $this->rate_limit('osm');
        
        // Overpass query for nearby businesses
        $overpass_query = "[out:json][timeout:10];\n";
        $overpass_query .= "(\n";
        $overpass_query .= "  node[\"amenity\"~\"restaurant|cafe|bar|pub\"](around:{$radius},{$lat},{$lon});\n";
        $overpass_query .= "  way[\"amenity\"~\"restaurant|cafe|bar|pub\"](around:{$radius},{$lat},{$lon});\n";
        $overpass_query .= ");\n";
        $overpass_query .= "out tags;";
        
        $response = wp_remote_post(self::OSM_OVERPASS_API, [
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => [
                'data' => $overpass_query
            ]
        ]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        $results = [];
        
        if (!empty($data['elements'])) {
            foreach ($data['elements'] as $element) {
                if (!empty($element['tags']['name'])) {
                    $results[] = [
                        'id' => $element['id'],
                        'type' => $element['type'],
                        'name' => $element['tags']['name'],
                        'tags' => $element['tags'],
                        'distance' => $this->calculate_distance(
                            $lat, $lon,
                            $element['lat'] ?? 0,
                            $element['lon'] ?? 0
                        )
                    ];
                }
            }
            
            // Sort by distance
            usort($results, function($a, $b) {
                return $a['distance'] <=> $b['distance'];
            });
        }
        
        return $results;
    }
    
    /**
     * Calculate distance between two points
     */
    private function calculate_distance( float $lat1, float $lon1, float $lat2, float $lon2 ): float {
        $earth_radius = 6371000; // meters
        
        $lat1_rad = deg2rad($lat1);
        $lat2_rad = deg2rad($lat2);
        $delta_lat = deg2rad($lat2 - $lat1);
        $delta_lon = deg2rad($lon2 - $lon1);
        
        $a = sin($delta_lat / 2) * sin($delta_lat / 2) +
             cos($lat1_rad) * cos($lat2_rad) *
             sin($delta_lon / 2) * sin($delta_lon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earth_radius * $c;
    }
}