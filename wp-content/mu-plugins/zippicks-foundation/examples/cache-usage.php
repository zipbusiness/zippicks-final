<?php
/**
 * ZipPicks Foundation - Cache Usage Examples
 * 
 * Demonstrates the enterprise caching capabilities for the $100B platform
 */

// Basic cache operations
$cache = cache();

// Simple get/put
cache_put('user_preferences', ['theme' => 'dark', 'language' => 'en'], 3600);
$preferences = cache_get('user_preferences');

// Remember pattern - only executes callback if not cached
$topRestaurants = cache_remember('top_restaurants_NYC', 1800, function() {
    // This expensive query only runs if data isn't cached
    global $wpdb;
    return $wpdb->get_results(
        "SELECT * FROM wp_zippicks_businesses 
         WHERE city = 'NYC' AND rating > 4.5 
         ORDER BY rating DESC LIMIT 10"
    );
});

// Stampede-safe pattern for hot data
$trendingVibes = cache_stampede_safe('trending_vibes_today', 300, function() {
    // Only one process will regenerate this at a time
    // Others will wait or use stale data
    return calculate_trending_vibes();
});

// Multi-store access
$redisCache = cache('redis');
$redisCache->put('fast_data', $value, 300);

$dbCache = cache('database');
$dbCache->forever('permanent_data', $value);

// Increment/decrement for counters
cache()->increment('page_views');
cache()->increment('api_calls', 5);
$currentViews = cache()->decrement('remaining_quota');

// Tagged caching (when using Redis)
$cache->tags(['restaurants', 'NYC'])->put('nyc_restaurants', $data, 3600);
$cache->tags(['restaurants', 'LA'])->put('la_restaurants', $data, 3600);

// Flush all NYC data
$cache->tags(['NYC'])->flush();

// Cache manager for advanced operations
$manager = cache_manager();

// Get statistics
$stats = $manager->getStatistics();
echo "Cache hit rate: " . $stats['wordpress']['efficiency']['hit_rate'] . "%\n";

// Warm cache with critical data
$manager->warm([
    'homepage_data' => fn() => get_homepage_data(),
    'vibe_taxonomy' => ['data' => get_vibe_taxonomy(), 'ttl' => 86400],
    'critic_scores' => fn() => get_all_critic_scores(),
]);

// Lock-based operations
$lock = $manager->lock('import_restaurants', 300);

if ($lock->acquire()) {
    try {
        // Perform exclusive operation
        import_restaurant_data();
    } finally {
        $lock->release();
    }
}

// Performance monitoring in action
$cache = cache();
$startTime = microtime(true);

// This will hit L1 (Array) cache first, then L2 (WordPress), then L3 (Redis)
$businessData = $cache->remember('business_12345', 3600, function() {
    return fetch_business_details(12345);
});

$elapsed = microtime(true) - $startTime;
echo "Cache operation took: " . ($elapsed * 1000) . "ms\n";

// Batch operations for efficiency
$keys = ['business_1', 'business_2', 'business_3'];
$businesses = $cache->many($keys);

$cache->putMany([
    'business_4' => $business4Data,
    'business_5' => $business5Data,
], 3600);

// Cache invalidation patterns
function update_business($id, $data) {
    // Update database
    update_business_in_db($id, $data);
    
    // Clear specific cache
    cache_forget("business_{$id}");
    
    // Clear related caches
    cache()->tags(['businesses', "city_{$data['city']}"])->flush();
}

// Real-world example: Taste Graph caching
class TasteGraphCache {
    public function getUserTaste($userId) {
        return cache_remember("taste_graph_user_{$userId}", 1800, function() use ($userId) {
            // Complex calculation only happens every 30 minutes
            return $this->calculateUserTasteProfile($userId);
        });
    }
    
    public function getBusinessMatch($userId, $businessId) {
        return cache_stampede_safe(
            "match_{$userId}_{$businessId}", 
            300,
            function() use ($userId, $businessId) {
                $userTaste = $this->getUserTaste($userId);
                $businessVibes = $this->getBusinessVibes($businessId);
                return $this->calculateMatch($userTaste, $businessVibes);
            }
        );
    }
}

// Helper for debugging cache behavior
if (WP_DEBUG) {
    add_action('shutdown', function() {
        $stats = cache_manager()->getStatistics();
        error_log('Cache Performance: ' . json_encode($stats));
    });
}