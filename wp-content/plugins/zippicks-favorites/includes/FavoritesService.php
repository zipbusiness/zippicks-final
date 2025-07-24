<?php
namespace ZipPicks\Favorites;

/**
 * Main favorites service class
 * This is registered with the ZipPicks foundation for use by other plugins
 */
class FavoritesService {
    
    private $api_client;
    private $cache;
    
    public function __construct($api_client, $cache) {
        $this->api_client = $api_client;
        $this->cache = $cache;
    }
    
    /**
     * Check if a business is favorited by a user
     */
    public function is_favorited($user_id, $business_id) {
        $cache_key = "favorited_{$user_id}_{$business_id}";
        
        return $this->cache->remember($cache_key, function() use ($user_id, $business_id) {
            try {
                $response = $this->api_client->get("/users/{$user_id}/favorites", [
                    'business_id' => $business_id,
                    'limit' => 1
                ]);
                
                return !empty($response['data']);
            } catch (\Exception $e) {
                return false;
            }
        }, 300);
    }
    
    /**
     * Get user's favorite count
     */
    public function get_favorite_count($user_id) {
        $cache_key = "favorite_count_{$user_id}";
        
        return $this->cache->remember($cache_key, function() use ($user_id) {
            try {
                $response = $this->api_client->get("/users/{$user_id}/favorites", [
                    'per_page' => 1
                ]);
                
                return $response['total'] ?? 0;
            } catch (\Exception $e) {
                return 0;
            }
        }, 3600);
    }
    
    /**
     * Get user's favorites
     */
    public function get_favorites($user_id, $params = []) {
        $cache_key = 'user_favs_' . $user_id . '_' . md5(serialize($params));
        
        return $this->cache->remember($cache_key, function() use ($user_id, $params) {
            return $this->api_client->get_user_favorites($user_id, $params);
        }, 300);
    }
    
    /**
     * Add favorite
     */
    public function add_favorite($user_id, $business_id, $context = []) {
        $result = $this->api_client->add_favorite($user_id, $business_id, $context);
        
        // Clear caches
        $this->cache->clear_user_cache($user_id);
        
        // Trigger action for other plugins
        do_action('zippicks_favorite_added', $user_id, $business_id, $result);
        
        return $result;
    }
    
    /**
     * Remove favorite
     */
    public function remove_favorite($user_id, $favorite_id) {
        $result = $this->api_client->remove_favorite($user_id, $favorite_id);
        
        // Clear caches
        $this->cache->clear_user_cache($user_id);
        
        // Trigger action for other plugins
        do_action('zippicks_favorite_removed', $user_id, $favorite_id);
        
        return $result;
    }
    
    /**
     * Get user's favorite cities
     */
    public function get_favorite_cities($user_id) {
        return $this->cache->remember("favorite_cities_{$user_id}", function() use ($user_id) {
            $response = $this->api_client->get_favorite_cities($user_id);
            return $response['data'] ?? [];
        }, 3600);
    }
    
    /**
     * Get favorites by location
     */
    public function get_favorites_by_location($user_id, $city, $state = null) {
        $params = ['city' => $city];
        if ($state) {
            $params['state'] = $state;
        }
        
        return $this->get_favorites($user_id, $params);
    }
    
    /**
     * Get nearby favorites
     */
    public function get_nearby_favorites($user_id, $lat, $lng, $radius = 5) {
        $cache_key = "nearby_favs_{$user_id}_{$lat}_{$lng}_{$radius}";
        
        return $this->cache->remember($cache_key, function() use ($user_id, $lat, $lng, $radius) {
            return $this->api_client->get_nearby_favorites($user_id, $lat, $lng, $radius);
        }, 600);
    }
    
    /**
     * Search favorites
     */
    public function search_favorites($user_id, $query, $params = []) {
        return $this->api_client->search_favorites($user_id, $query, $params);
    }
    
    /**
     * Get favorite button HTML
     */
    public function get_favorite_button($business_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return '';
        }
        
        $is_favorited = $this->is_favorited($user_id, $business_id);
        
        return sprintf(
            '<button class="zp-favorite-btn %s" data-business-id="%s" aria-label="%s">
                <span class="zp-favorite-icon">%s</span>
                <span class="zp-favorite-text">%s</span>
            </button>',
            $is_favorited ? 'is-favorited' : '',
            esc_attr($business_id),
            $is_favorited ? __('Remove from favorites', 'zippicks-favorites') : __('Save to favorites', 'zippicks-favorites'),
            $is_favorited ? '♥' : '♡',
            $is_favorited ? __('Saved', 'zippicks-favorites') : __('Save', 'zippicks-favorites')
        );
    }
    
    /**
     * Get user's taste profile based on favorites
     */
    public function get_taste_profile($user_id) {
        $cache_key = "taste_profile_{$user_id}";
        
        return $this->cache->remember($cache_key, function() use ($user_id) {
            try {
                // This would be a dedicated API endpoint in production
                $favorites = $this->api_client->get_user_favorites($user_id, ['per_page' => 100]);
                
                // Analyze favorites for patterns
                $vibes = [];
                $cuisines = [];
                $price_ranges = [];
                
                foreach ($favorites['data'] as $fav) {
                    if (!empty($fav['primary_vibe'])) {
                        $vibes[] = $fav['primary_vibe'];
                    }
                    if (!empty($fav['cuisine_type'])) {
                        $cuisines[] = $fav['cuisine_type'];
                    }
                    if (!empty($fav['price_range'])) {
                        $price_ranges[] = $fav['price_range'];
                    }
                }
                
                return [
                    'top_vibes' => array_slice(array_count_values($vibes), 0, 5),
                    'top_cuisines' => array_slice(array_count_values($cuisines), 0, 5),
                    'avg_price_range' => $price_ranges ? array_sum($price_ranges) / count($price_ranges) : 0,
                    'total_favorites' => $favorites['total']
                ];
                
            } catch (\Exception $e) {
                return null;
            }
        }, 86400); // Cache for 24 hours
    }
}