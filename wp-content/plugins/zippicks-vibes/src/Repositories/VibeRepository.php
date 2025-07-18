<?php
/**
 * Vibe Repository Implementation
 * 
 * Secure data access layer with caching and logging
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\Repositories;

use ZipPicksVibes\Models\PaginatedResult;

/**
 * Class VibeRepository
 */
class VibeRepository implements VibeRepositoryInterface {
    
    /**
     * Database instance
     * 
     * @var mixed
     */
    private $database;
    
    /**
     * Cache instance
     * 
     * @var mixed
     */
    private $cache;
    
    /**
     * Logger instance
     * 
     * @var mixed
     */
    private $logger;
    
    /**
     * Table names
     * 
     * @var array
     */
    private array $tables;
    
    /**
     * Constructor
     * 
     * @param mixed $database Foundation database service or null
     * @param mixed $cache Foundation cache service or null
     * @param mixed $logger Foundation logger service or null
     */
    public function __construct($database = null, $cache = null, $logger = null) {
        global $wpdb;
        
        $this->database = $database;
        $this->cache = $cache;
        $this->logger = $logger;
        
        // Define table names
        $this->tables = [
            'vibes' => $wpdb->prefix . 'zippicks_vibes',
            'categories' => $wpdb->prefix . 'zippicks_vibe_categories',
            'assignments' => $wpdb->prefix . 'zippicks_vibe_category_assignments',
            'waitlist' => $wpdb->prefix . 'zippicks_waitlist'
        ];
    }
    
    /**
     * Find vibe by ID
     */
    public function find(int $id): ?object {
        $cache_key = 'zippicks_vibe_' . $id;
        
        // Try cache first
        if ($this->cache) {
            $cached = $this->cache->get($cache_key);
            // Validate cached data is actually an object before returning
            if ($cached !== false && is_object($cached)) {
                return $cached;
            }
        }
        
        global $wpdb;
        $vibe = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tables['vibes']} WHERE id = %d LIMIT 1",
            $id
        ));
        
        if ($vibe && $this->cache) {
            $this->cache->set($cache_key, $vibe, ZIPPICKS_VIBES_CACHE_TTL);
        }
        
        return $vibe;
    }
    
    /**
     * Find vibe by slug
     */
    public function findBySlug(string $slug): ?object {
        $cache_key = 'zippicks_vibe_slug_' . md5($slug);
        
        // Try cache first
        if ($this->cache) {
            $cached = $this->cache->get($cache_key);
            // Validate cached data is actually an object before returning
            if ($cached !== false && is_object($cached)) {
                return $cached;
            }
        }
        
        global $wpdb;
        $vibe = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tables['vibes']} WHERE slug = %s AND is_active = 1 LIMIT 1",
            $slug
        ));
        
        if ($vibe && $this->cache) {
            $this->cache->set($cache_key, $vibe, ZIPPICKS_VIBES_CACHE_TTL);
        }
        
        return $vibe;
    }
    
    /**
     * Get all vibes
     */
    public function findAll(array $args = []): array {
        $defaults = [
            'status' => 'active',
            'orderby' => 'order_position',
            'order' => 'ASC',
            'limit' => -1,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        $cache_key = 'zippicks_vibes_all_' . md5(serialize($args));
        
        // Try cache first
        if ($this->cache) {
            $cached = $this->cache->get($cache_key);
            // Only return cached data if it's actually an array
            if ($cached !== false && is_array($cached)) {
                return $cached;
            }
        }
        
        global $wpdb;
        
        // Build query
        $query = "SELECT * FROM {$this->tables['vibes']} WHERE 1=1";
        
        if ($args['status'] === 'active') {
            $query .= " AND is_active = 1";
        } elseif ($args['status'] === 'inactive') {
            $query .= " AND is_active = 0";
        }
        // If status is 'all' or anything else, don't filter by status
        
        // Order by
        $allowed_orderby = ['order_position', 'name', 'created_at', 'updated_at'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'order_position';
        $order = $args['order'] === 'DESC' ? 'DESC' : 'ASC';
        $query .= " ORDER BY $orderby $order";
        
        // Limit
        if ($args['limit'] > 0) {
            $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        $vibes = $wpdb->get_results($query);
        
        // Ensure we always return an array
        $result = is_array($vibes) ? $vibes : [];
        
        // Log warning if database query failed
        if ($vibes === null && $this->logger) {
            $this->logger->warning('VibeRepository::findAll() - Database query returned null', [
                'query' => $query,
                'args' => $args,
                'wpdb_error' => $wpdb->last_error
            ]);
        }
        
        if ($this->cache) {
            $this->cache->set($cache_key, $result, ZIPPICKS_VIBES_CACHE_TTL);
        }
        
        return $result;
    }
    
    /**
     * Get paginated vibes
     */
    public function findPaginated(int $page = 1, int $perPage = 20, array $args = []): PaginatedResult {
        $defaults = [
            'status' => 'active',
            'orderby' => 'order_position',
            'order' => 'ASC'
        ];
        
        $args = wp_parse_args($args, $defaults);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage)); // Limit to max 100 per page
        
        // Get total count first
        $countArgs = [];
        if ($args['status'] === 'active') {
            $countArgs['active'] = true;
        } elseif ($args['status'] === 'inactive') {
            $countArgs['active'] = false;
        }
        // If status is 'all', count all vibes
        $total = $this->count($countArgs);
        
        // Calculate offset
        $offset = ($page - 1) * $perPage;
        
        // If offset is beyond total, return empty result
        if ($offset >= $total && $total > 0) {
            return new PaginatedResult([], $total, $perPage, $page);
        }
        
        // Cache key for paginated results
        $cache_key = 'zippicks_vibes_paginated_' . md5(serialize([
            'page' => $page,
            'per_page' => $perPage,
            'args' => $args
        ]));
        
        // Try cache first
        if ($this->cache) {
            $cached = $this->cache->get($cache_key);
            if ($cached !== false && $cached instanceof PaginatedResult) {
                return $cached;
            }
        }
        
        global $wpdb;
        
        // Build query
        $query = "SELECT * FROM {$this->tables['vibes']} WHERE 1=1";
        
        if ($args['status'] === 'active') {
            $query .= " AND is_active = 1";
        } elseif ($args['status'] === 'inactive') {
            $query .= " AND is_active = 0";
        }
        // If status is 'all' or anything else, no additional WHERE clause
        
        // Order by
        $allowed_orderby = ['order_position', 'name', 'created_at', 'updated_at'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'order_position';
        $order = $args['order'] === 'DESC' ? 'DESC' : 'ASC';
        $query .= " ORDER BY $orderby $order";
        
        // Add pagination
        $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $perPage, $offset);
        
        $vibes = $wpdb->get_results($query);
        
        // Create paginated result
        $result = new PaginatedResult(
            $vibes,
            $total,
            $perPage,
            $page,
            [
                'orderby' => $orderby,
                'order' => $order,
                'status' => $args['status']
            ]
        );
        
        // Cache the result
        if ($this->cache) {
            $this->cache->set($cache_key, $result, ZIPPICKS_VIBES_CACHE_TTL);
        }
        
        return $result;
    }
    
    /**
     * Get vibes by category
     */
    public function findByCategory(int $categoryId, array $args = []): array {
        $cache_key = 'zippicks_vibes_category_' . $categoryId . '_' . md5(serialize($args));
        
        // Try cache first
        if ($this->cache) {
            $cached = $this->cache->get($cache_key);
            // Only return cached data if it's actually an array
            if ($cached !== false && is_array($cached)) {
                return $cached;
            }
        }
        
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT v.* FROM {$this->tables['vibes']} v
             INNER JOIN {$this->tables['assignments']} a ON v.id = a.vibe_id
             WHERE a.category_id = %d AND v.is_active = 1
             ORDER BY v.order_position ASC, v.name ASC",
            $categoryId
        );
        
        $vibes = $wpdb->get_results($query);
        
        // Handle null result from database error
        if ($vibes === null) {
            $vibes = [];
        }
        
        if ($this->cache) {
            $this->cache->set($cache_key, $vibes, ZIPPICKS_VIBES_CACHE_TTL);
        }
        
        return $vibes;
    }
    
    /**
     * Get vibes with categories
     */
    public function findAllWithCategories(array $args = []): array {
        $vibes = $this->findAll($args);
        
        // Enhance each vibe with its categories
        foreach ($vibes as &$vibe) {
            $vibe->categories = $this->getVibeCategories($vibe->id);
        }
        
        return $vibes;
    }
    
    /**
     * Create new vibe
     */
    public function create(array $data) {
        global $wpdb;
        
        // Prepare data
        $insert_data = [
            'name' => sanitize_text_field($data['name'] ?? ''),
            'slug' => sanitize_title($data['slug'] ?? $data['name'] ?? ''),
            'description' => wp_kses_post($data['description'] ?? ''),
            'icon' => $data['icon'] ?? '⭐', // Don't sanitize emojis
            'color' => sanitize_hex_color($data['color'] ?? '#000000'),
            'order_position' => absint($data['order_position'] ?? 0),
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($this->tables['vibes'], $insert_data);
        
        if ($result === false) {
            
            if ($this->logger) {
                $this->logger->error('Failed to create vibe', ['data' => $data, 'error' => $wpdb->last_error]);
            }
            
            // Throw exception with detailed error
            throw new \Exception('Database insert failed: ' . $wpdb->last_error);
        }
        
        $vibe_id = $wpdb->insert_id;
        
        // Clear cache
        $this->clearCache();
        
        // Log activity
        if ($this->logger) {
            $this->logger->info('Vibe created', ['id' => $vibe_id, 'name' => $insert_data['name']]);
        }
        
        return $vibe_id;
    }
    
    /**
     * Update vibe
     */
    public function update(int $id, array $data): bool {
        global $wpdb;
        
        // Prepare data
        $update_data = [];
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['slug'])) {
            $update_data['slug'] = sanitize_title($data['slug']);
        }
        if (isset($data['description'])) {
            $update_data['description'] = wp_kses_post($data['description']);
        }
        if (isset($data['icon'])) {
            // Keep the icon value as-is to preserve special characters like emojis
            $update_data['icon'] = $data['icon'];
        }
        if (isset($data['color'])) {
            $update_data['color'] = sanitize_hex_color($data['color']);
        }
        if (isset($data['order_position'])) {
            $update_data['order_position'] = absint($data['order_position']);
        }
        if (isset($data['is_active'])) {
            $update_data['is_active'] = $data['is_active'] ? 1 : 0;
        }
        
        $update_data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $this->tables['vibes'],
            $update_data,
            ['id' => $id]
        );
        
        if ($result === false) {
            if ($this->logger) {
                $this->logger->error('Failed to update vibe', ['id' => $id, 'error' => $wpdb->last_error]);
            }
            return false;
        }
        
        // Clear cache
        $this->clearCache($id);
        
        // Log activity
        if ($this->logger) {
            $this->logger->info('Vibe updated', ['id' => $id]);
        }
        
        return true;
    }
    
    /**
     * Delete vibe
     */
    public function delete(int $id): bool {
        global $wpdb;
        
        // Delete category assignments first
        $wpdb->delete($this->tables['assignments'], ['vibe_id' => $id]);
        
        // Delete vibe
        $result = $wpdb->delete($this->tables['vibes'], ['id' => $id]);
        
        if ($result === false) {
            if ($this->logger) {
                $this->logger->error('Failed to delete vibe', ['id' => $id, 'error' => $wpdb->last_error]);
            }
            return false;
        }
        
        // Clear cache
        $this->clearCache($id);
        
        // Log activity
        if ($this->logger) {
            $this->logger->info('Vibe deleted', ['id' => $id]);
        }
        
        return true;
    }
    
    /**
     * Update vibe order
     */
    public function updateOrder(array $order): bool {
        global $wpdb;
        
        $success = true;
        
        foreach ($order as $position => $vibeId) {
            $result = $wpdb->update(
                $this->tables['vibes'],
                ['order_position' => $position],
                ['id' => $vibeId]
            );
            
            if ($result === false) {
                $success = false;
                if ($this->logger) {
                    $this->logger->error('Failed to update vibe order', ['id' => $vibeId, 'position' => $position]);
                }
            }
        }
        
        // Clear cache
        $this->clearCache();
        
        return $success;
    }
    
    /**
     * Get categories for a vibe
     */
    public function getVibeCategories(int $vibeId): array {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT c.* FROM {$this->tables['categories']} c
             INNER JOIN {$this->tables['assignments']} a ON c.id = a.category_id
             WHERE a.vibe_id = %d
             ORDER BY c.name ASC",
            $vibeId
        ));
        
        // Handle null result from database error
        return $results === null ? [] : $results;
    }
    
    /**
     * Assign categories to vibe
     */
    public function assignCategories(int $vibeId, array $categoryIds): bool {
        global $wpdb;
        
        // Log the assignment attempt
        error_log("ZipPicks Vibes: Assigning categories to vibe $vibeId: " . json_encode($categoryIds));
        
        // Delete existing assignments
        $delete_result = $wpdb->delete($this->tables['assignments'], ['vibe_id' => $vibeId]);
        error_log("ZipPicks Vibes: Deleted existing assignments for vibe $vibeId. Result: " . ($delete_result !== false ? 'success' : 'failed'));
        
        // Insert new assignments
        if (!empty($categoryIds)) {
            foreach ($categoryIds as $categoryId) {
                $insert_result = $wpdb->insert(
                    $this->tables['assignments'],
                    [
                        'vibe_id' => $vibeId,
                        'category_id' => $categoryId
                    ]
                );
                
                if ($insert_result === false) {
                    error_log("ZipPicks Vibes: Failed to insert assignment - vibe_id: $vibeId, category_id: $categoryId, error: " . $wpdb->last_error);
                } else {
                    error_log("ZipPicks Vibes: Successfully inserted assignment - vibe_id: $vibeId, category_id: $categoryId");
                }
            }
        } else {
            error_log("ZipPicks Vibes: No categories to assign for vibe $vibeId");
        }
        
        // Clear cache
        $this->clearCache($vibeId);
        
        return true;
    }
    
    /**
     * Get all categories
     */
    public function getAllCategories(): array {
        global $wpdb;
        
        $cache_key = 'zippicks_vibe_categories_all';
        
        // Try cache first, but only return if we have actual data
        if ($this->cache) {
            $cached = $this->cache->get($cache_key);
            // Only use cached data if it's a non-empty array
            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }
        }
        
        $categories = $wpdb->get_results(
            "SELECT * FROM {$this->tables['categories']} ORDER BY name ASC"
        );
        
        // Ensure we always return an array
        $result = is_array($categories) ? $categories : [];
        
        // Log warning if database query failed
        if ($categories === null && $this->logger) {
            $this->logger->warning('VibeRepository::getAllCategories() - Database query returned null', [
                'table' => $this->tables['categories'],
                'wpdb_error' => $wpdb->last_error
            ]);
        }
        
        // Only cache if we have actual results, otherwise delete the cache
        if ($this->cache) {
            if (!empty($result)) {
                $this->cache->set($cache_key, $result, ZIPPICKS_VIBES_CACHE_TTL);
            } else {
                // Delete any existing empty cache
                $this->cache->delete($cache_key);
            }
        }
        
        return $result;
    }
    
    /**
     * Get all active vibes
     */
    public function getAll(): array {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->tables['vibes']} 
             WHERE is_active = %d 
             ORDER BY order_position ASC",
            1
        );
        
        $results = $wpdb->get_results($sql);
        
        // Handle null result from database error
        return $results === null ? [] : $results;
    }
    
    /**
     * Search vibes
     */
    public function search(string $query, array $args = []): array {
        global $wpdb;
        
        $search_query = '%' . $wpdb->esc_like($query) . '%';
        
        // Parse arguments with defaults
        $limit = isset($args['limit']) ? (int) $args['limit'] : 20;
        $active_only = isset($args['active_only']) ? (bool) $args['active_only'] : true;
        
        $where_clause = $active_only ? 'WHERE is_active = %d' : 'WHERE 1=1';
        $prepare_args = $active_only ? [1, $search_query, $search_query, $limit] : [$search_query, $search_query, $limit];
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->tables['vibes']} 
             {$where_clause}
             AND (name LIKE %s OR slug LIKE %s)
             ORDER BY order_position ASC
             LIMIT %d",
            ...$prepare_args
        );
        
        $results = $wpdb->get_results($sql);
        
        // Handle null result from database error
        return $results === null ? [] : $results;
    }
    
    /**
     * Search vibes with pagination
     */
    public function searchPaginated(string $query, int $page = 1, int $perPage = 20, array $args = []): PaginatedResult {
        global $wpdb;
        
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $search_query = '%' . $wpdb->esc_like($query) . '%';
        
        // Get total count first
        $count_sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['vibes']} 
             WHERE is_active = 1 
             AND (name LIKE %s OR description LIKE %s)",
            $search_query,
            $search_query
        );
        
        $total = (int) $wpdb->get_var($count_sql);
        
        // Calculate offset
        $offset = ($page - 1) * $perPage;
        
        // If offset is beyond total, return empty result
        if ($offset >= $total && $total > 0) {
            return new PaginatedResult([], $total, $perPage, $page);
        }
        
        // Cache key for search results
        $cache_key = 'zippicks_vibes_search_' . md5(serialize([
            'query' => $query,
            'page' => $page,
            'per_page' => $perPage,
            'args' => $args
        ]));
        
        // Try cache first
        if ($this->cache) {
            $cached = $this->cache->get($cache_key);
            if ($cached !== false && $cached instanceof PaginatedResult) {
                return $cached;
            }
        }
        
        // Get paginated results
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->tables['vibes']} 
             WHERE is_active = 1 
             AND (name LIKE %s OR description LIKE %s)
             ORDER BY 
                CASE WHEN name LIKE %s THEN 1 ELSE 2 END,
                name ASC
             LIMIT %d OFFSET %d",
            $search_query,
            $search_query,
            $search_query,
            $perPage,
            $offset
        );
        
        $vibes = $wpdb->get_results($sql);
        
        // Handle null result from database error
        if ($vibes === null) {
            $vibes = [];
        }
        
        // Create paginated result
        $result = new PaginatedResult(
            $vibes,
            $total,
            $perPage,
            $page,
            [
                'query' => $query,
                'search_type' => 'vibes'
            ]
        );
        
        // Cache the result
        if ($this->cache) {
            $this->cache->set($cache_key, $result, ZIPPICKS_VIBES_CACHE_TTL);
        }
        
        return $result;
    }
    
    /**
     * Get vibe count
     */
    public function count(array $args = []): int {
        // Cache key for count
        $cache_key = 'zippicks_vibes_count_' . md5(serialize($args));
        
        // Try cache first
        if ($this->cache) {
            $cached = $this->cache->get($cache_key);
            if ($cached !== false && $cached !== null) {
                return (int) $cached;
            }
        }
        
        global $wpdb;
        
        $query = "SELECT COUNT(*) FROM {$this->tables['vibes']} WHERE 1=1";
        
        if (isset($args['active']) && $args['active']) {
            $query .= " AND is_active = 1";
        }
        
        $count = (int) $wpdb->get_var($query);
        
        // Cache the count
        if ($this->cache) {
            // Cache count for shorter time since it changes more frequently
            $this->cache->set($cache_key, $count, 60); // 1 minute cache
        }
        
        return $count;
    }
    
    /**
     * Log waitlist entry
     */
    public function logWaitlist(array $data) {
        global $wpdb;
        
        $insert_data = [
            'user_id' => absint($data['user_id'] ?? 0) ?: null,
            'ip_address' => sanitize_text_field($data['ip_address'] ?? $_SERVER['REMOTE_ADDR']),
            'email' => sanitize_email($data['email'] ?? ''),
            'zip_code' => sanitize_text_field($data['zip_code'] ?? ''),
            'city' => sanitize_text_field($data['city'] ?? ''),
            'state' => sanitize_text_field($data['state'] ?? ''),
            'vibe_id' => absint($data['vibe_id'] ?? 0),
            'vibe_slug' => sanitize_title($data['vibe_slug'] ?? ''),
            'vibe_name' => sanitize_text_field($data['vibe_name'] ?? ''),
            'created_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($this->tables['waitlist'], $insert_data);
        
        if ($result === false) {
            if ($this->logger) {
                $this->logger->error('Failed to log waitlist entry', ['error' => $wpdb->last_error]);
            }
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get popular vibes
     */
    public function getPopular(int $limit = 10, string $zipCode = ''): array {
        global $wpdb;
        
        $cache_key = 'zippicks_vibes_popular_' . $limit . '_' . md5($zipCode);
        
        // Try cache first
        if ($this->cache) {
            $cached = $this->cache->get($cache_key);
            // Only return cached data if it's actually an array
            if ($cached !== false && is_array($cached)) {
                return $cached;
            }
        }
        
        if ($zipCode) {
            // Get popular vibes for specific ZIP
            $sql = $wpdb->prepare(
                "SELECT v.*, COUNT(w.id) as waitlist_count
                 FROM {$this->tables['vibes']} v
                 LEFT JOIN {$this->tables['waitlist']} w ON v.id = w.vibe_id AND w.zip_code = %s
                 WHERE v.is_active = 1
                 GROUP BY v.id
                 ORDER BY waitlist_count DESC, v.order_position ASC
                 LIMIT %d",
                $zipCode,
                $limit
            );
        } else {
            // Get globally popular vibes
            $sql = $wpdb->prepare(
                "SELECT v.*, COUNT(w.id) as waitlist_count
                 FROM {$this->tables['vibes']} v
                 LEFT JOIN {$this->tables['waitlist']} w ON v.id = w.vibe_id
                 WHERE v.is_active = 1
                 GROUP BY v.id
                 ORDER BY waitlist_count DESC, v.order_position ASC
                 LIMIT %d",
                $limit
            );
        }
        
        $vibes = $wpdb->get_results($sql);
        
        // Handle null result from database error
        if ($vibes === null) {
            $vibes = [];
        }
        
        if ($this->cache) {
            $this->cache->set($cache_key, $vibes, ZIPPICKS_VIBES_CACHE_TTL);
        }
        
        return $vibes;
    }
    
    /**
     * Clear cache
     */
    private function clearCache(?int $vibeId = null): void {
        if (!$this->cache) {
            return;
        }
        
        if ($vibeId) {
            // Clear specific vibe cache
            $this->cache->delete('zippicks_vibe_' . $vibeId);
            
            // Also clear slug-based cache for this vibe
            $vibe = $this->find($vibeId);
            if ($vibe && isset($vibe->slug)) {
                $this->cache->delete('zippicks_vibe_slug_' . md5($vibe->slug));
            }
        }
        
        // Clear all vibe-related caches  
        // If the cache supports group flushing, use it
        if (method_exists($this->cache, 'flushGroup')) {
            $this->cache->flushGroup('vibes');
            $this->cache->flushGroup('queries'); // Clear query caches
        } else {
            // Otherwise, clear known patterns
            // This is less efficient but ensures compatibility
            if (method_exists($this->cache, 'deleteMultiple')) {
                // Try to delete common cache keys
                $patterns = [
                    'zippicks_vibes_all_',
                    'zippicks_vibes_paginated_',
                    'zippicks_vibes_category_',
                    'zippicks_vibes_search_',
                    'zippicks_vibes_count_',
                    'zippicks_vibes_popular_',
                    'zippicks_vibe_categories_all'
                ];
                
                // Note: This is a simplified approach
                // In production, you might want to track actual cache keys
            }
        }
    }
    
    /**
     * Create a new category
     * 
     * @param array $data Category data
     * @return int Category ID
     */
    public function createCategory(array $data): int {
        global $wpdb;
        
        $result = $wpdb->insert($this->tables['categories'], $data);
        
        if ($result === false || $wpdb->last_error) {
            throw new \Exception('Failed to create category: ' . ($wpdb->last_error ?: 'Database insert failed'));
        }
        
        $categoryId = $wpdb->insert_id;
        
        // Ensure we always return an integer
        if (!is_numeric($categoryId) || $categoryId <= 0) {
            throw new \Exception('Invalid category ID returned from database');
        }
        
        // Clear cache
        if ($this->cache) {
            $this->cache->delete('zippicks_vibe_categories_all');
        }
        
        return (int) $categoryId;
    }
    
    /**
     * Update a category
     * 
     * @param int $categoryId Category ID
     * @param array $data Category data
     * @return bool Success status
     */
    public function updateCategory(int $categoryId, array $data): bool {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->tables['categories'],
            $data,
            ['id' => $categoryId]
        );
        
        if ($wpdb->last_error) {
            throw new \Exception('Failed to update category: ' . $wpdb->last_error);
        }
        
        // Clear cache
        if ($this->cache) {
            $this->cache->delete('zippicks_vibe_categories_all');
        }
        
        return $result !== false;
    }
    
    /**
     * Delete a category
     * 
     * @param int $categoryId Category ID
     * @return bool Success status
     */
    public function deleteCategory(int $categoryId): bool {
        global $wpdb;
        
        // First remove all category assignments
        $wpdb->delete($this->tables['assignments'], ['category_id' => $categoryId]);
        
        // Then delete the category
        $result = $wpdb->delete($this->tables['categories'], ['id' => $categoryId]);
        
        if ($wpdb->last_error) {
            throw new \Exception('Failed to delete category: ' . $wpdb->last_error);
        }
        
        // Clear cache
        if ($this->cache) {
            $this->cache->delete('zippicks_vibe_categories_all');
        }
        
        return $result !== false;
    }
    
    /**
     * Get a single category by ID
     * 
     * @param int $categoryId Category ID
     * @return object|null Category object or null if not found
     */
    public function getCategory(int $categoryId): ?object {
        global $wpdb;
        
        $category = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tables['categories']} WHERE id = %d",
            $categoryId
        ));
        
        return $category;
    }
}