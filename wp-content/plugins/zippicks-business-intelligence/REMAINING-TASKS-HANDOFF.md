# Business Intelligence Plugin - Remaining Tasks Handoff

## Overview
This document outlines the remaining tasks for the ZipPicks Business Intelligence plugin after implementing structured logging, debug panel, and enhanced error log viewer.

## ✅ Completed Tasks Summary

1. **Structured Logging System** (`src/Services/LoggerService.php`)
   - Full API request/response logging with context
   - Integration with Foundation logger
   - Statistics and cleanup methods
   - Enhanced database schema with indexes

2. **Debug Panel** (`views/admin/debug-panel.php`)
   - System information display
   - Configuration validation
   - API health checks
   - Real-time statistics
   - Export functionality

3. **Enhanced Error Log Viewer** (`views/admin/logs.php`)
   - Advanced filtering system
   - Visual status indicators
   - Expandable details
   - CSV export
   - Bulk cleanup

4. **Response Time Tracking**
   - Implemented in LoggerService
   - Tracks all API response times
   - Calculates averages by endpoint

5. **API Usage Statistics**
   - Implemented in LoggerService::get_statistics()
   - Tracks requests by endpoint
   - Error rates and response times

## 🔧 Remaining Tasks

### 1. Cache Inspection Tool
**Priority**: Medium  
**Estimated Time**: 2-3 hours

#### Requirements
- View all cached entries with metadata
- Search/filter by cache key pattern
- View cache entry details (value, TTL, size)
- Delete individual cache entries
- Bulk operations (delete by pattern)

#### Implementation Plan

1. **Add Cache Inspector Submenu** in `AdminDashboard::add_admin_menu()`:
```php
add_submenu_page(
    'zippicks-business-intelligence',
    __('Cache Inspector', 'zippicks-business-intelligence'),
    __('Cache', 'zippicks-business-intelligence'),
    'manage_business_intelligence',
    'zippicks-bi-cache',
    [$this, 'display_cache_inspector']
);
```

2. **Create Cache Inspector Method** in `AdminDashboard`:
```php
public function display_cache_inspector() {
    // Get cache entries from Redis or transients
    $cache_entries = $this->services['cache']->get_all_keys();
    
    // Handle search/filter
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    
    // Handle delete actions
    if (isset($_POST['action']) && $_POST['action'] === 'delete_cache_key') {
        // Delete specific key
    }
    
    include ZIPPICKS_BI_PLUGIN_DIR . 'views/admin/cache-inspector.php';
}
```

3. **Enhance CacheService** with new methods:
```php
// Get all cache keys (Redis or transient)
public function get_all_keys(string $pattern = '*'): array

// Get cache entry details
public function get_entry_details(string $key): array

// Delete by pattern
public function delete_by_pattern(string $pattern): int

// Get cache entry size
public function get_entry_size(string $key): int
```

4. **Create Cache Inspector View** (`views/admin/cache-inspector.php`):
- Search bar with pattern matching
- Table with columns: Key, Type, Size, TTL, Actions
- Modal/expandable view for cache values
- Bulk selection and deletion
- Cache statistics summary

### 2. Manual Cache Clearing by Key Pattern
**Priority**: Medium  
**Estimated Time**: 1-2 hours

#### Requirements
- Clear cache entries matching specific patterns
- Support wildcards (e.g., `bi_restaurant_*`, `bi_location_90210_*`)
- Confirmation before deletion
- Show count of affected entries
- Log cache clearing actions

#### Implementation Plan

1. **Add Pattern Clearing to CacheService**:
```php
public function clear_by_pattern(string $pattern): int {
    $cleared = 0;
    
    if ($this->redis_available) {
        // Redis SCAN with pattern
        $iterator = null;
        while ($keys = $this->redis->scan($iterator, $this->prefix . $pattern)) {
            foreach ($keys as $key) {
                $this->redis->del($key);
                $cleared++;
            }
        }
    } else {
        // WordPress transients - need to query options table
        global $wpdb;
        $like_pattern = str_replace('*', '%', $pattern);
        $keys = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            '_transient_' . $this->prefix . $like_pattern
        ));
        
        foreach ($keys as $key) {
            $transient_name = str_replace('_transient_', '', $key);
            delete_transient($transient_name);
            $cleared++;
        }
    }
    
    // Log the action
    if (isset($this->logger)) {
        $this->logger->log(
            LoggerService::LEVEL_INFO,
            'Cache cleared by pattern',
            ['pattern' => $pattern, 'cleared' => $cleared]
        );
    }
    
    return $cleared;
}
```

2. **Add UI Controls** in Debug Panel or Cache Inspector:
```html
<form method="post" action="">
    <input type="text" name="cache_pattern" placeholder="e.g., bi_restaurant_*" />
    <button type="submit" name="clear_pattern" class="button">Clear by Pattern</button>
</form>
```

3. **Add AJAX Handler** for async clearing:
```php
public function ajax_clear_cache_pattern() {
    check_ajax_referer('zippicks_bi_ajax', 'nonce');
    
    $pattern = sanitize_text_field($_POST['pattern'] ?? '');
    if (empty($pattern)) {
        wp_send_json_error(['message' => 'Pattern required']);
    }
    
    $cleared = $this->services['cache']->clear_by_pattern($pattern);
    wp_send_json_success([
        'message' => sprintf('Cleared %d cache entries', $cleared),
        'count' => $cleared
    ]);
}
```

### 3. Cache Hit Rate Monitoring
**Priority**: Low  
**Estimated Time**: 2-3 hours

#### Requirements
- Track cache hits vs misses
- Calculate hit rates by time period
- Visualize cache performance
- Alert on low hit rates
- Track by cache key type

#### Implementation Plan

1. **Create Cache Metrics Table**:
```sql
CREATE TABLE {prefix}zippicks_bi_cache_metrics (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    cache_key varchar(255) NOT NULL,
    key_type varchar(50),
    action enum('hit', 'miss', 'set', 'delete'),
    response_time float,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY cache_key (cache_key),
    KEY key_type (key_type),
    KEY created_at (created_at)
);
```

2. **Update CacheService** to track metrics:
```php
private function track_metric(string $key, string $action, float $time = 0) {
    if (!$this->config->get('track_cache_metrics', false)) {
        return;
    }
    
    global $wpdb;
    
    // Extract key type (bi_restaurant_, bi_location_, etc)
    $key_type = $this->extract_key_type($key);
    
    $wpdb->insert(
        $wpdb->prefix . 'zippicks_bi_cache_metrics',
        [
            'cache_key' => substr($key, 0, 255),
            'key_type' => $key_type,
            'action' => $action,
            'response_time' => $time,
            'created_at' => current_time('mysql')
        ]
    );
}

// Update get() method
public function get(string $key) {
    $start = microtime(true);
    $value = $this->get_from_cache($key);
    $duration = microtime(true) - $start;
    
    $this->track_metric($key, $value !== false ? 'hit' : 'miss', $duration);
    
    return $value;
}
```

3. **Add Cache Analytics Methods**:
```php
public function get_hit_rate_stats(string $period = 'hour'): array {
    global $wpdb;
    
    $interval = match($period) {
        'hour' => '1 HOUR',
        'day' => '1 DAY',
        'week' => '7 DAY',
        default => '1 HOUR'
    };
    
    $stats = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            key_type,
            SUM(CASE WHEN action = 'hit' THEN 1 ELSE 0 END) as hits,
            SUM(CASE WHEN action = 'miss' THEN 1 ELSE 0 END) as misses,
            AVG(response_time) as avg_response_time
         FROM {$wpdb->prefix}zippicks_bi_cache_metrics
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL %s)
         GROUP BY key_type",
        $interval
    ), ARRAY_A);
    
    // Calculate hit rates
    foreach ($stats as &$stat) {
        $total = $stat['hits'] + $stat['misses'];
        $stat['hit_rate'] = $total > 0 ? ($stat['hits'] / $total) * 100 : 0;
        $stat['total_requests'] = $total;
    }
    
    return $stats;
}
```

4. **Create Cache Performance Dashboard**:
- Add to Debug Panel or separate page
- Show hit rates by cache type
- Time-series graph (if using chart library)
- Performance recommendations
- Alert thresholds configuration

5. **Add Scheduled Cleanup**:
```php
// In Activator::schedule_events()
if (!wp_next_scheduled('zippicks_bi_cleanup_cache_metrics')) {
    wp_schedule_event(time(), 'daily', 'zippicks_bi_cleanup_cache_metrics');
}

// Cleanup old metrics (keep 7 days)
public function cleanup_cache_metrics() {
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->prefix}zippicks_bi_cache_metrics 
         WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );
}
```

## 📋 Testing Checklist

### Cache Inspection Tool
- [ ] View all cache entries
- [ ] Search works with wildcards
- [ ] Individual entry deletion
- [ ] Bulk operations
- [ ] Redis and transient support
- [ ] Proper permissions check

### Pattern-based Cache Clearing
- [ ] Pattern matching works correctly
- [ ] Confirmation before clearing
- [ ] Accurate count of cleared entries
- [ ] Works with both Redis and transients
- [ ] Action is logged

### Cache Hit Rate Monitoring
- [ ] Metrics are tracked accurately
- [ ] Hit rates calculate correctly
- [ ] Performance impact is minimal
- [ ] Old metrics are cleaned up
- [ ] Statistics display properly

## 🚀 Next Steps

1. **Immediate Priority**: Implement Cache Inspection Tool as it provides immediate value for debugging
2. **Follow Up**: Add pattern-based clearing for maintenance operations
3. **Long Term**: Implement hit rate monitoring for performance optimization

## 📝 Notes

- All remaining tasks integrate with existing services (CacheService, LoggerService)
- UI components follow existing admin panel patterns
- Consider adding these features to the main dashboard widget for quick access
- Test thoroughly with both Redis and WordPress transient fallback

## 🔌 Integration Points

- **CacheService**: Primary integration point for all cache operations
- **LoggerService**: Log all cache operations for audit trail
- **AdminDashboard**: UI integration for new features
- **ConfigService**: Add new configuration options as needed

This completes the handoff documentation for the remaining Business Intelligence plugin tasks.