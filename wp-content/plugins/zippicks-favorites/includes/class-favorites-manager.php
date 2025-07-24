<?php
namespace ZipPicks\Favorites;

if (!defined('ABSPATH')) {
    exit;
}

class Favorites_Manager {
    
    private $location_service;
    
    public function __construct() {
        $this->location_service = new Location_Service();
    }
    
    /**
     * Save a business as favorite
     */
    public function save_favorite($user_id, $business_id, $notes = '') {
        global $wpdb;
        $table = Database::get_favorites_table();
        
        // Check if already favorited
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND business_id = %d",
            $user_id,
            $business_id
        ));
        
        if ($existing) {
            // Update notes if provided
            if ($notes) {
                $wpdb->update(
                    $table,
                    ['user_notes' => $notes],
                    ['id' => $existing->id]
                );
            }
            
            return ['id' => $existing->id, 'updated' => true];
        }
        
        // Get business location data
        $location = $this->location_service->get_business_location($business_id);
        
        // Insert new favorite
        $data = [
            'user_id' => $user_id,
            'business_id' => $business_id,
            'user_notes' => $notes,
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'city' => $location['city'],
            'state' => $location['state'],
            'country' => $location['country'],
            'neighborhood' => $location['neighborhood'],
            'zip_code' => $location['zip_code']
        ];
        
        $result = $wpdb->insert($table, $data);
        
        if ($result === false) {
            return new \WP_Error('db_error', 'Failed to save favorite');
        }
        
        $favorite_id = $wpdb->insert_id;
        
        // Trigger action for other plugins
        do_action('zippicks_favorite_saved', $favorite_id, $user_id, $business_id);
        
        // Log analytics
        $this->log_favorite_action('save', $user_id, $business_id, $location);
        
        return ['id' => $favorite_id, 'created' => true];
    }
    
    /**
     * Remove a favorite
     */
    public function remove_favorite($user_id, $favorite_id) {
        global $wpdb;
        $table = Database::get_favorites_table();
        
        // Verify ownership
        $favorite = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d",
            $favorite_id,
            $user_id
        ));
        
        if (!$favorite) {
            return new \WP_Error('not_found', 'Favorite not found');
        }
        
        $result = $wpdb->delete($table, [
            'id' => $favorite_id,
            'user_id' => $user_id
        ]);
        
        if ($result === false) {
            return new \WP_Error('db_error', 'Failed to remove favorite');
        }
        
        // Trigger action
        do_action('zippicks_favorite_removed', $favorite_id, $user_id, $favorite->business_id);
        
        // Log analytics
        $this->log_favorite_action('remove', $user_id, $favorite->business_id);
        
        return true;
    }
    
    /**
     * Get user's favorites with filters
     */
    public function get_user_favorites($user_id, $args = []) {
        global $wpdb;
        $table = Database::get_favorites_table();
        
        $defaults = [
            'page' => 1,
            'per_page' => 20,
            'city' => '',
            'state' => '',
            'neighborhood' => '',
            'cuisine' => '',
            'search' => '',
            'sort' => 'date'
        ];
        
        $args = wp_parse_args($args, $defaults);
        $where_clauses = ["f.user_id = %d"];
        $where_values = [$user_id];
        
        // Apply filters
        if ($args['city']) {
            $where_clauses[] = "f.city = %s";
            $where_values[] = $args['city'];
        }
        
        if ($args['state']) {
            $where_clauses[] = "f.state = %s";
            $where_values[] = $args['state'];
        }
        
        if ($args['neighborhood']) {
            $where_clauses[] = "f.neighborhood = %s";
            $where_values[] = $args['neighborhood'];
        }
        
        // Join with business posts for search and cuisine filter
        $join_clause = "LEFT JOIN {$wpdb->posts} p ON f.business_id = p.ID";
        
        if ($args['search']) {
            $where_clauses[] = "(p.post_title LIKE %s OR f.user_notes LIKE %s)";
            $search_like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_like;
            $where_values[] = $search_like;
        }
        
        // Build query
        $where_sql = implode(' AND ', $where_clauses);
        
        // Determine sort order
        $order_by = match($args['sort']) {
            'name' => 'p.post_title ASC',
            'distance' => 'f.latitude ASC', // Will be enhanced with actual distance calculation
            'rating' => 'rating DESC', // Assumes rating meta
            default => 'f.saved_date DESC'
        };
        
        // Get total count
        $count_query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT f.id) FROM $table f $join_clause WHERE $where_sql",
            $where_values
        );
        $total = $wpdb->get_var($count_query);
        
        // Calculate pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        // Get favorites
        $query = $wpdb->prepare(
            "SELECT f.*, p.post_title, p.post_status 
             FROM $table f 
             $join_clause 
             WHERE $where_sql 
             ORDER BY $order_by 
             LIMIT %d OFFSET %d",
            array_merge($where_values, [$args['per_page'], $offset])
        );
        
        $favorites = $wpdb->get_results($query);
        
        // Enhance with business data
        $enhanced_favorites = [];
        foreach ($favorites as $favorite) {
            $business_data = $this->get_business_data($favorite->business_id);
            
            $enhanced_favorites[] = [
                'id' => $favorite->id,
                'business_id' => $favorite->business_id,
                'saved_date' => $favorite->saved_date,
                'user_notes' => $favorite->user_notes,
                'latitude' => $favorite->latitude,
                'longitude' => $favorite->longitude,
                'city' => $favorite->city,
                'state' => $favorite->state,
                'neighborhood' => $favorite->neighborhood,
                'zip_code' => $favorite->zip_code,
                'business' => $business_data
            ];
        }
        
        return [
            'data' => $enhanced_favorites,
            'total' => intval($total),
            'page' => intval($args['page']),
            'per_page' => intval($args['per_page']),
            'total_pages' => ceil($total / $args['per_page'])
        ];
    }
    
    /**
     * Get favorites within a radius
     */
    public function get_favorites_within_radius($user_id, $lat, $lng, $radius_km) {
        global $wpdb;
        $table = Database::get_favorites_table();
        
        // Use Haversine formula for distance calculation
        $query = $wpdb->prepare("
            SELECT f.*, 
                   p.post_title,
                   (6371 * acos(
                       cos(radians(%f)) * cos(radians(f.latitude)) * 
                       cos(radians(f.longitude) - radians(%f)) + 
                       sin(radians(%f)) * sin(radians(f.latitude))
                   )) AS distance_km
            FROM $table f
            LEFT JOIN {$wpdb->posts} p ON f.business_id = p.ID
            WHERE f.user_id = %d
            AND f.latitude IS NOT NULL 
            AND f.longitude IS NOT NULL
            HAVING distance_km <= %d
            ORDER BY distance_km ASC
        ", $lat, $lng, $lat, $user_id, $radius_km);
        
        $favorites = $wpdb->get_results($query);
        
        // Enhance with business data
        $enhanced_favorites = [];
        foreach ($favorites as $favorite) {
            $business_data = $this->get_business_data($favorite->business_id);
            
            $enhanced_favorites[] = [
                'id' => $favorite->id,
                'business_id' => $favorite->business_id,
                'saved_date' => $favorite->saved_date,
                'user_notes' => $favorite->user_notes,
                'latitude' => $favorite->latitude,
                'longitude' => $favorite->longitude,
                'city' => $favorite->city,
                'state' => $favorite->state,
                'neighborhood' => $favorite->neighborhood,
                'distance_km' => round($favorite->distance_km, 2),
                'distance_mi' => round($favorite->distance_km * 0.621371, 2),
                'business' => $business_data
            ];
        }
        
        return [
            'data' => $enhanced_favorites,
            'center' => ['lat' => $lat, 'lng' => $lng],
            'radius_km' => $radius_km,
            'total' => count($enhanced_favorites)
        ];
    }
    
    /**
     * Get favorites by city
     */
    public function get_favorites_by_city($user_id, $city, $state = null) {
        $args = ['city' => $city];
        if ($state) {
            $args['state'] = $state;
        }
        
        return $this->get_user_favorites($user_id, $args);
    }
    
    /**
     * Search favorites
     */
    public function search_favorites($user_id, $query, $location = null) {
        $args = ['search' => $query];
        
        if ($location) {
            // Parse location string (e.g., "Los Angeles, CA")
            $parts = array_map('trim', explode(',', $location));
            if (count($parts) >= 2) {
                $args['city'] = $parts[0];
                $args['state'] = $parts[1];
            } else {
                $args['city'] = $location;
            }
        }
        
        return $this->get_user_favorites($user_id, $args);
    }
    
    /**
     * Get user's favorite cities
     */
    public function get_user_favorite_cities($user_id) {
        global $wpdb;
        $table = Database::get_favorites_table();
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT city, state, COUNT(*) as count
            FROM $table
            WHERE user_id = %d 
            AND city IS NOT NULL 
            AND state IS NOT NULL
            GROUP BY city, state
            ORDER BY count DESC, city ASC
        ", $user_id));
        
        return array_map(function($row) {
            return [
                'city' => $row->city,
                'state' => $row->state,
                'count' => intval($row->count),
                'display_name' => $row->city . ', ' . $row->state
            ];
        }, $results);
    }
    
    /**
     * Check if a business is favorited
     */
    public function is_favorited($user_id, $business_id) {
        global $wpdb;
        $table = Database::get_favorites_table();
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND business_id = %d",
            $user_id,
            $business_id
        ));
        
        return $exists > 0;
    }
    
    /**
     * Get business data
     */
    private function get_business_data($business_id) {
        $post = get_post($business_id);
        
        if (!$post || $post->post_status !== 'publish') {
            return null;
        }
        
        // Get all meta data
        $meta = get_post_meta($business_id);
        
        // Get vibes
        $vibes = wp_get_post_terms($business_id, 'business_vibe', ['fields' => 'names']);
        
        // Get cuisine
        $cuisines = wp_get_post_terms($business_id, 'business_category', ['fields' => 'names']);
        
        // Get featured image
        $image_url = get_the_post_thumbnail_url($business_id, 'medium');
        
        return [
            'id' => $business_id,
            'name' => $post->post_title,
            'slug' => $post->post_name,
            'description' => $post->post_excerpt,
            'cuisine' => $cuisines ? $cuisines[0] : '',
            'vibes' => $vibes,
            'address' => $meta['address'][0] ?? '',
            'phone' => $meta['phone'][0] ?? '',
            'website' => $meta['website'][0] ?? '',
            'price_range' => $meta['price_range'][0] ?? '',
            'rating' => floatval($meta['rating'][0] ?? 0),
            'review_count' => intval($meta['review_count'][0] ?? 0),
            'image_url' => $image_url,
            'url' => get_permalink($business_id)
        ];
    }
    
    /**
     * Log favorite actions for analytics
     */
    private function log_favorite_action($action, $user_id, $business_id, $location = []) {
        if (!get_option('zippicks_favorites_track_analytics', true)) {
            return;
        }
        
        // Log to custom table or use existing analytics service
        do_action('zippicks_analytics_track', 'favorite_' . $action, [
            'user_id' => $user_id,
            'business_id' => $business_id,
            'city' => $location['city'] ?? null,
            'state' => $location['state'] ?? null,
            'timestamp' => current_time('mysql')
        ]);
    }
}