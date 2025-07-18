<?php
/**
 * ZIP-Aware Intelligence Engine
 * 
 * Handles location-based intelligence, demand tracking,
 * and geographic personalization for the platform.
 * 
 * @package ZipPicks\Foundation
 */

namespace ZipPicks\Foundation;

if (!defined('ABSPATH')) {
    exit;
}

class ZipIntelligence {
    
    /**
     * Geolocation service endpoint
     * 
     * @var string
     */
    private $geo_endpoint = 'https://api.zippicks.com/geo/v1';
    
    /**
     * ZIP code data cache
     * 
     * @var array
     */
    private $zip_cache = [];
    
    /**
     * Default search radius (miles)
     * 
     * @var int
     */
    private $default_radius = 5;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Session and cookie management
        add_action('init', [$this, 'init_session']);
        add_action('wp_ajax_zippicks_set_location', [$this, 'ajax_set_location']);
        add_action('wp_ajax_nopriv_zippicks_set_location', [$this, 'ajax_set_location']);
        
        // Query modifications
        add_action('pre_get_posts', [$this, 'modify_queries_for_zip']);
        add_filter('posts_where', [$this, 'add_proximity_search'], 10, 2);
        
        // Demand tracking
        add_action('zippicks_search_performed', [$this, 'track_search_demand'], 10, 2);
        add_action('zippicks_business_viewed', [$this, 'track_view_demand'], 10, 2);
        
        // Scheduled tasks
        add_action('zippicks_hourly_maintenance', [$this, 'process_demand_data']);
    }
    
    /**
     * Initialize session for ZIP tracking
     */
    public function init_session() {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
    }
    
    /**
     * Get current user's ZIP code
     * 
     * @return string|null ZIP code
     */
    public function get_user_zip() {
        // Priority order: Session > Cookie > User Meta > IP Geolocation > Default
        
        // 1. Check session
        if (isset($_SESSION['zippicks_zip'])) {
            return $this->validate_zip($_SESSION['zippicks_zip']);
        }
        
        // 2. Check cookie
        if (isset($_COOKIE['zippicks_zip'])) {
            $zip = $this->validate_zip($_COOKIE['zippicks_zip']);
            if ($zip) {
                $_SESSION['zippicks_zip'] = $zip;
                return $zip;
            }
        }
        
        // 3. Check user meta if logged in
        if (is_user_logged_in()) {
            $user_zip = get_user_meta(get_current_user_id(), 'zippicks_preferred_zip', true);
            if ($user_zip) {
                $zip = $this->validate_zip($user_zip);
                if ($zip) {
                    $this->set_user_zip($zip);
                    return $zip;
                }
            }
        }
        
        // 4. Try IP geolocation
        $geo_zip = $this->get_zip_from_ip();
        if ($geo_zip) {
            $this->set_user_zip($geo_zip, false); // Don't save to user meta
            return $geo_zip;
        }
        
        // 5. Return default
        return apply_filters('zippicks_default_zip', '10001');
    }
    
    /**
     * Set user's ZIP code
     * 
     * @param string $zip ZIP code
     * @param bool $save_to_profile Save to user profile
     */
    public function set_user_zip($zip, $save_to_profile = true) {
        $zip = $this->validate_zip($zip);
        
        if (!$zip) {
            return false;
        }
        
        // Set in session
        $_SESSION['zippicks_zip'] = $zip;
        
        // Set cookie (30 days)
        setcookie('zippicks_zip', $zip, time() + (30 * DAY_IN_SECONDS), '/', COOKIE_DOMAIN, is_ssl(), true);
        
        // Save to user meta if logged in
        if ($save_to_profile && is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'zippicks_preferred_zip', $zip);
        }
        
        // Track ZIP change
        do_action('zippicks_zip_changed', $zip, get_current_user_id());
        
        return true;
    }
    
    /**
     * Validate ZIP code
     * 
     * @param string $zip ZIP code
     * @return string|null Validated ZIP
     */
    private function validate_zip($zip) {
        // Basic US ZIP validation
        $zip = preg_replace('/[^0-9]/', '', $zip);
        
        if (strlen($zip) === 5 || strlen($zip) === 9) {
            return substr($zip, 0, 5);
        }
        
        return null;
    }
    
    /**
     * Get ZIP from IP address
     * 
     * @return string|null ZIP code
     */
    private function get_zip_from_ip() {
        $ip = $this->get_user_ip();
        
        if (!$ip) {
            return null;
        }
        
        // Check cache first
        $cache_key = 'zippicks_ip_zip_' . md5($ip);
        $cached_zip = get_transient($cache_key);
        
        if ($cached_zip !== false) {
            return $cached_zip;
        }
        
        // Call geolocation service
        $response = wp_remote_get($this->geo_endpoint . '/ip/' . $ip);
        
        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($data['zip'])) {
                $zip = $this->validate_zip($data['zip']);
                set_transient($cache_key, $zip, DAY_IN_SECONDS);
                return $zip;
            }
        }
        
        return null;
    }
    
    /**
     * Get user IP address
     * 
     * @return string|null IP address
     */
    private function get_user_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get ZIP code data
     * 
     * @param string $zip ZIP code
     * @return array ZIP data
     */
    public function get_zip_data($zip) {
        $zip = $this->validate_zip($zip);
        
        if (!$zip) {
            return null;
        }
        
        // Check cache
        if (isset($this->zip_cache[$zip])) {
            return $this->zip_cache[$zip];
        }
        
        // Check database
        global $wpdb;
        $table = ZIPPICKS_TABLE_PREFIX . 'zip_data';
        
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE zip_code = %s",
            $zip
        ), ARRAY_A);
        
        if (!$data) {
            // Fetch from API
            $data = $this->fetch_zip_data_from_api($zip);
            
            if ($data) {
                // Store in database
                $wpdb->insert($table, $data);
            }
        }
        
        // Cache in memory
        $this->zip_cache[$zip] = $data;
        
        return $data;
    }
    
    /**
     * Fetch ZIP data from API
     * 
     * @param string $zip ZIP code
     * @return array|null ZIP data
     */
    private function fetch_zip_data_from_api($zip) {
        $response = wp_remote_get($this->geo_endpoint . '/zip/' . $zip);
        
        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($data['zip_code'])) {
                return [
                    'zip_code' => $data['zip_code'],
                    'city' => $data['city'] ?? '',
                    'state' => $data['state'] ?? '',
                    'county' => $data['county'] ?? '',
                    'latitude' => $data['latitude'] ?? 0,
                    'longitude' => $data['longitude'] ?? 0,
                    'timezone' => $data['timezone'] ?? 'America/New_York',
                    'population' => $data['population'] ?? 0,
                    'median_income' => $data['median_income'] ?? 0
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Get nearby ZIP codes
     * 
     * @param string $zip Center ZIP
     * @param int $radius Radius in miles
     * @return array ZIP codes
     */
    public function get_nearby_zips($zip, $radius = null) {
        $zip_data = $this->get_zip_data($zip);
        
        if (!$zip_data) {
            return [$zip];
        }
        
        $radius = $radius ?: $this->default_radius;
        
        global $wpdb;
        $table = ZIPPICKS_TABLE_PREFIX . 'zip_data';
        
        // Haversine formula for distance calculation
        $results = $wpdb->get_col($wpdb->prepare("
            SELECT zip_code 
            FROM {$table}
            WHERE (
                3959 * acos(
                    cos(radians(%f)) * cos(radians(latitude)) * 
                    cos(radians(longitude) - radians(%f)) + 
                    sin(radians(%f)) * sin(radians(latitude))
                )
            ) <= %d
            ORDER BY zip_code
        ", 
            $zip_data['latitude'],
            $zip_data['longitude'],
            $zip_data['latitude'],
            $radius
        ));
        
        return array_unique(array_merge([$zip], $results));
    }
    
    /**
     * Calculate distance between ZIPs
     * 
     * @param string $zip1 First ZIP
     * @param string $zip2 Second ZIP
     * @return float Distance in miles
     */
    public function calculate_distance($zip1, $zip2) {
        $data1 = $this->get_zip_data($zip1);
        $data2 = $this->get_zip_data($zip2);
        
        if (!$data1 || !$data2) {
            return 0;
        }
        
        return $this->haversine_distance(
            $data1['latitude'],
            $data1['longitude'],
            $data2['latitude'],
            $data2['longitude']
        );
    }
    
    /**
     * Haversine distance calculation
     * 
     * @param float $lat1 Latitude 1
     * @param float $lon1 Longitude 1
     * @param float $lat2 Latitude 2
     * @param float $lon2 Longitude 2
     * @return float Distance in miles
     */
    private function haversine_distance($lat1, $lon1, $lat2, $lon2) {
        $earth_radius = 3959; // miles
        
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
        
        return $earth_radius * $c;
    }
    
    /**
     * Modify queries for ZIP filtering
     * 
     * @param WP_Query $query Query object
     */
    public function modify_queries_for_zip($query) {
        // Only modify main queries and business queries
        if (!$query->is_main_query() && !$query->get('zippicks_zip_filter')) {
            return;
        }
        
        // Skip admin queries
        if (is_admin()) {
            return;
        }
        
        // Check if querying businesses
        $post_types = $query->get('post_type');
        if (!is_array($post_types)) {
            $post_types = [$post_types];
        }
        
        if (!in_array('zippicks_business', $post_types)) {
            return;
        }
        
        // Get ZIP context
        $zip = $query->get('zippicks_zip') ?: $this->get_user_zip();
        $radius = $query->get('zippicks_radius') ?: $this->default_radius;
        
        // Get nearby ZIPs
        $nearby_zips = $this->get_nearby_zips($zip, $radius);
        
        // Add meta query for ZIP filtering
        $meta_query = $query->get('meta_query') ?: [];
        $meta_query[] = [
            'key' => '_zippicks_zip',
            'value' => $nearby_zips,
            'compare' => 'IN'
        ];
        
        $query->set('meta_query', $meta_query);
        
        // Store ZIP data for proximity ordering
        $query->set('zippicks_center_zip', $zip);
        $query->set('zippicks_nearby_zips', $nearby_zips);
    }
    
    /**
     * Add proximity search to WHERE clause
     * 
     * @param string $where WHERE clause
     * @param WP_Query $query Query object
     * @return string Modified WHERE
     */
    public function add_proximity_search($where, $query) {
        $center_zip = $query->get('zippicks_center_zip');
        
        if (!$center_zip || !$query->get('zippicks_order_by_distance')) {
            return $where;
        }
        
        // This would add distance calculation to the query
        // For now, return unmodified
        return $where;
    }
    
    /**
     * Track search demand
     * 
     * @param string $query Search query
     * @param array $context Search context
     */
    public function track_search_demand($query, $context) {
        $zip = $context['zip'] ?? $this->get_user_zip();
        
        global $wpdb;
        $table = ZIPPICKS_TABLE_PREFIX . 'demand_tracking';
        
        $wpdb->insert($table, [
            'zip_code' => $zip,
            'demand_type' => 'search',
            'demand_data' => json_encode([
                'query' => $query,
                'vibes' => $context['vibes'] ?? [],
                'category' => $context['category'] ?? '',
                'results_count' => $context['results_count'] ?? 0
            ]),
            'user_id' => get_current_user_id() ?: null,
            'timestamp' => current_time('mysql')
        ]);
    }
    
    /**
     * Track view demand
     * 
     * @param int $business_id Business ID
     * @param array $context View context
     */
    public function track_view_demand($business_id, $context) {
        $business_zip = get_post_meta($business_id, '_zippicks_zip', true);
        $user_zip = $context['user_zip'] ?? $this->get_user_zip();
        
        global $wpdb;
        $table = ZIPPICKS_TABLE_PREFIX . 'demand_tracking';
        
        $wpdb->insert($table, [
            'zip_code' => $business_zip,
            'demand_type' => 'view',
            'demand_data' => json_encode([
                'business_id' => $business_id,
                'user_zip' => $user_zip,
                'referrer' => $context['referrer'] ?? '',
                'source' => $context['source'] ?? 'organic'
            ]),
            'user_id' => get_current_user_id() ?: null,
            'timestamp' => current_time('mysql')
        ]);
    }
    
    /**
     * Get demand heatmap data
     * 
     * @param array $args Query arguments
     * @return array Heatmap data
     */
    public function get_demand_heatmap($args = []) {
        $defaults = [
            'days' => 30,
            'type' => 'all',
            'min_demand' => 10,
            'limit' => 100
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        global $wpdb;
        $table = ZIPPICKS_TABLE_PREFIX . 'demand_tracking';
        
        $type_clause = '';
        if ($args['type'] !== 'all') {
            $type_clause = $wpdb->prepare(' AND demand_type = %s', $args['type']);
        }
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                zip_code,
                COUNT(*) as demand_count,
                demand_type,
                COUNT(DISTINCT user_id) as unique_users
            FROM {$table}
            WHERE timestamp > DATE_SUB(NOW(), INTERVAL %d DAY)
            {$type_clause}
            GROUP BY zip_code, demand_type
            HAVING demand_count >= %d
            ORDER BY demand_count DESC
            LIMIT %d
        ", 
            $args['days'],
            $args['min_demand'],
            $args['limit']
        ));
        
        $heatmap = [];
        foreach ($results as $row) {
            if (!isset($heatmap[$row->zip_code])) {
                $zip_data = $this->get_zip_data($row->zip_code);
                $heatmap[$row->zip_code] = [
                    'zip' => $row->zip_code,
                    'city' => $zip_data['city'] ?? '',
                    'state' => $zip_data['state'] ?? '',
                    'lat' => $zip_data['latitude'] ?? 0,
                    'lng' => $zip_data['longitude'] ?? 0,
                    'demand' => []
                ];
            }
            
            $heatmap[$row->zip_code]['demand'][$row->demand_type] = [
                'count' => intval($row->demand_count),
                'unique_users' => intval($row->unique_users)
            ];
        }
        
        return array_values($heatmap);
    }
    
    /**
     * Get trending searches by ZIP
     * 
     * @param string $zip ZIP code
     * @param array $args Query arguments
     * @return array Trending searches
     */
    public function get_trending_by_zip($zip, $args = []) {
        $defaults = [
            'days' => 7,
            'limit' => 10
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        global $wpdb;
        $table = ZIPPICKS_TABLE_PREFIX . 'demand_tracking';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                demand_data,
                COUNT(*) as search_count
            FROM {$table}
            WHERE 
                zip_code = %s 
                AND demand_type = 'search'
                AND timestamp > DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY demand_data
            ORDER BY search_count DESC
            LIMIT %d
        ", 
            $zip,
            $args['days'],
            $args['limit']
        ));
        
        $trending = [];
        foreach ($results as $row) {
            $data = json_decode($row->demand_data, true);
            if (isset($data['query'])) {
                $trending[] = [
                    'query' => $data['query'],
                    'count' => intval($row->search_count),
                    'vibes' => $data['vibes'] ?? []
                ];
            }
        }
        
        return $trending;
    }
    
    /**
     * AJAX handler for setting location
     */
    public function ajax_set_location() {
        check_ajax_referer('zippicks_ajax', 'nonce');
        
        $zip = isset($_POST['zip']) ? sanitize_text_field($_POST['zip']) : '';
        
        if ($this->set_user_zip($zip)) {
            wp_send_json_success([
                'zip' => $zip,
                'data' => $this->get_zip_data($zip)
            ]);
        } else {
            wp_send_json_error(__('Invalid ZIP code', 'zippicks-foundation'));
        }
    }
    
    /**
     * Process demand data (scheduled task)
     */
    public function process_demand_data() {
        // Aggregate demand data into insights
        global $wpdb;
        $table = ZIPPICKS_TABLE_PREFIX . 'demand_tracking';
        $insights_table = ZIPPICKS_TABLE_PREFIX . 'demand_insights';
        
        // Calculate hourly aggregates
        $wpdb->query("
            INSERT INTO {$insights_table} (zip_code, period_type, period_value, demand_data, created_at)
            SELECT 
                zip_code,
                'hourly' as period_type,
                DATE_FORMAT(timestamp, '%Y-%m-%d %H:00:00') as period_value,
                JSON_OBJECT(
                    'total_demand', COUNT(*),
                    'unique_users', COUNT(DISTINCT user_id),
                    'search_count', SUM(CASE WHEN demand_type = 'search' THEN 1 ELSE 0 END),
                    'view_count', SUM(CASE WHEN demand_type = 'view' THEN 1 ELSE 0 END)
                ) as demand_data,
                NOW() as created_at
            FROM {$table}
            WHERE timestamp > DATE_SUB(NOW(), INTERVAL 2 HOUR)
            GROUP BY zip_code, period_value
            ON DUPLICATE KEY UPDATE
                demand_data = VALUES(demand_data),
                created_at = VALUES(created_at)
        ");
        
        // Clean old raw data (keep 30 days)
        $wpdb->query("
            DELETE FROM {$table}
            WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
    }
    
    /**
     * Get ZIP intelligence summary
     * 
     * @param string $zip ZIP code
     * @return array Intelligence data
     */
    public function get_zip_intelligence($zip) {
        $zip_data = $this->get_zip_data($zip);
        
        if (!$zip_data) {
            return null;
        }
        
        return [
            'zip' => $zip,
            'location' => [
                'city' => $zip_data['city'],
                'state' => $zip_data['state'],
                'county' => $zip_data['county']
            ],
            'demographics' => [
                'population' => number_format($zip_data['population']),
                'median_income' => '$' . number_format($zip_data['median_income'])
            ],
            'demand' => [
                'trending_searches' => $this->get_trending_by_zip($zip),
                'peak_hours' => $this->get_peak_hours_by_zip($zip),
                'popular_vibes' => $this->get_popular_vibes_by_zip($zip)
            ],
            'competition' => [
                'business_count' => $this->get_business_count_by_zip($zip),
                'avg_score' => $this->get_average_score_by_zip($zip)
            ]
        ];
    }
    
    /**
     * Get peak hours by ZIP
     * 
     * @param string $zip ZIP code
     * @return array Peak hours
     */
    private function get_peak_hours_by_zip($zip) {
        global $wpdb;
        $table = ZIPPICKS_TABLE_PREFIX . 'demand_insights';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                HOUR(period_value) as hour,
                AVG(JSON_EXTRACT(demand_data, '$.total_demand')) as avg_demand
            FROM {$table}
            WHERE 
                zip_code = %s 
                AND period_type = 'hourly'
                AND period_value > DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY hour
            ORDER BY avg_demand DESC
            LIMIT 3
        ", $zip));
        
        $peak_hours = [];
        foreach ($results as $row) {
            $peak_hours[] = [
                'hour' => intval($row->hour),
                'label' => date('g A', strtotime($row->hour . ':00')),
                'demand' => round($row->avg_demand)
            ];
        }
        
        return $peak_hours;
    }
    
    /**
     * Get popular vibes by ZIP
     * 
     * @param string $zip ZIP code
     * @return array Popular vibes
     */
    private function get_popular_vibes_by_zip($zip) {
        global $wpdb;
        
        // Query businesses in ZIP and their vibes
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                t.name as vibe_name,
                t.slug as vibe_slug,
                COUNT(DISTINCT p.ID) as business_count
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE 
                p.post_type = 'zippicks_business'
                AND p.post_status = 'publish'
                AND pm.meta_key = '_zippicks_zip'
                AND pm.meta_value = %s
                AND tt.taxonomy = 'zippicks_vibe'
            GROUP BY t.term_id
            ORDER BY business_count DESC
            LIMIT 5
        ", $zip));
        
        $vibes = [];
        foreach ($results as $row) {
            $vibes[] = [
                'name' => $row->vibe_name,
                'slug' => $row->vibe_slug,
                'count' => intval($row->business_count)
            ];
        }
        
        return $vibes;
    }
    
    /**
     * Get business count by ZIP
     * 
     * @param string $zip ZIP code
     * @return int Business count
     */
    private function get_business_count_by_zip($zip) {
        global $wpdb;
        
        return intval($wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE 
                p.post_type = 'zippicks_business'
                AND p.post_status = 'publish'
                AND pm.meta_key = '_zippicks_zip'
                AND pm.meta_value = %s
        ", $zip)));
    }
    
    /**
     * Get average score by ZIP
     * 
     * @param string $zip ZIP code
     * @return float Average score
     */
    private function get_average_score_by_zip($zip) {
        global $wpdb;
        
        $avg = $wpdb->get_var($wpdb->prepare("
            SELECT AVG(pm2.meta_value)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
            WHERE 
                p.post_type = 'zippicks_business'
                AND p.post_status = 'publish'
                AND pm1.meta_key = '_zippicks_zip'
                AND pm1.meta_value = %s
                AND pm2.meta_key = '_zippicks_master_score'
        ", $zip));
        
        return round(floatval($avg), 1);
    }
}