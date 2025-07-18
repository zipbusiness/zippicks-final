# Master Critic Integration - ZipPicks Business Plugin Engineering Handoff

## Overview
Update the ZipPicks Business plugin to properly store and display verified restaurant data from the ZipBusiness API, including zpid tracking, vibe associations, and enriched metadata. This ensures businesses created by Master Critic are properly linked to their API data.

## Core Requirements

### 1. Database Schema Extensions

#### A. Update Business Post Meta Structure

Add new meta fields to store API data:

```php
// In class-business-post-type.php or similar

// Register new meta fields
register_post_meta('zippicks_business', 'zpid', [
    'type' => 'string',
    'description' => 'ZipBusiness API identifier',
    'single' => true,
    'show_in_rest' => true,
    'auth_callback' => function() {
        return current_user_can('edit_posts');
    }
]);

register_post_meta('zippicks_business', 'api_verified', [
    'type' => 'boolean',
    'description' => 'Whether business is verified via API',
    'single' => true,
    'default' => false,
    'show_in_rest' => true
]);

register_post_meta('zippicks_business', 'api_confidence_score', [
    'type' => 'number',
    'description' => 'API confidence score (0-1)',
    'single' => true,
    'show_in_rest' => true
]);

register_post_meta('zippicks_business', 'api_enriched_data', [
    'type' => 'string',
    'description' => 'Cached enriched data from API',
    'single' => true,
    'show_in_rest' => false // Large JSON data
]);

register_post_meta('zippicks_business', 'api_vibes', [
    'type' => 'string',
    'description' => 'JSON array of API vibe associations',
    'single' => true,
    'show_in_rest' => true
]);

register_post_meta('zippicks_business', 'last_api_sync', [
    'type' => 'string',
    'description' => 'Last API data synchronization timestamp',
    'single' => true,
    'show_in_rest' => true
]);
```

#### B. Create Vibe Storage Table

```sql
CREATE TABLE wp_zippicks_business_vibes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT NOT NULL,
    vibe_slug VARCHAR(100) NOT NULL,
    confidence_score FLOAT NOT NULL,
    category VARCHAR(50),
    source VARCHAR(20) DEFAULT 'api',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_business_vibe (business_id, vibe_slug),
    INDEX idx_business (business_id),
    INDEX idx_vibe (vibe_slug),
    INDEX idx_confidence (confidence_score)
);
```

### 2. ACF Field Updates

Add new field group for API data:

```php
// In ACF configuration or admin

$api_fields = [
    [
        'key' => 'field_zpid',
        'label' => 'ZipBusiness ID',
        'name' => 'zpid',
        'type' => 'text',
        'readonly' => 1,
        'instructions' => 'Unique identifier from ZipBusiness API'
    ],
    [
        'key' => 'field_api_verified',
        'label' => 'API Verified',
        'name' => 'api_verified',
        'type' => 'true_false',
        'ui' => 1,
        'ui_on_text' => 'Verified',
        'ui_off_text' => 'Unverified'
    ],
    [
        'key' => 'field_verified_vibes',
        'label' => 'Verified Vibes',
        'name' => 'verified_vibes',
        'type' => 'repeater',
        'sub_fields' => [
            [
                'key' => 'vibe_name',
                'label' => 'Vibe',
                'name' => 'vibe_name',
                'type' => 'text'
            ],
            [
                'key' => 'confidence',
                'label' => 'Confidence',
                'name' => 'confidence',
                'type' => 'number',
                'min' => 0,
                'max' => 1
            ]
        ]
    ]
];
```

### 3. Business Display Updates

#### A. Single Business Template Updates

Update `single-zippicks_business.php`:

```php
<?php
// Get API verification status
$is_verified = get_post_meta(get_the_ID(), 'api_verified', true);
$zpid = get_post_meta(get_the_ID(), 'zpid', true);
$api_vibes = json_decode(get_post_meta(get_the_ID(), 'api_vibes', true), true);
?>

<!-- Verification Badge -->
<?php if ($is_verified && $zpid): ?>
<div class="business-verification">
    <span class="verified-badge">
        <svg width="16" height="16" viewBox="0 0 16 16">
            <path d="M8 0L10.5 2.5L14 3L14.5 6.5L16 9L14.5 11.5L14 15L10.5 13.5L8 16L5.5 13.5L2 15L1.5 11.5L0 9L1.5 6.5L2 3L5.5 2.5L8 0Z" fill="#22C55E"/>
            <path d="M11.5 5.5L7 10L4.5 7.5" stroke="white" stroke-width="2"/>
        </svg>
        Verified by ZipBusiness
    </span>
</div>
<?php endif; ?>

<!-- Vibe Display -->
<?php if (!empty($api_vibes)): ?>
<div class="business-vibes">
    <h3>The Vibe</h3>
    <div class="vibe-tags">
        <?php 
        // Sort by confidence and show top 5
        usort($api_vibes, function($a, $b) {
            return $b['confidence'] - $a['confidence'];
        });
        
        foreach (array_slice($api_vibes, 0, 5) as $vibe): 
            if ($vibe['confidence'] >= 0.6): // Only show high confidence
        ?>
            <span class="vibe-tag" data-confidence="<?php echo esc_attr($vibe['confidence']); ?>">
                <?php echo esc_html($vibe['display_name']); ?>
            </span>
        <?php 
            endif;
        endforeach; 
        ?>
    </div>
</div>
<?php endif; ?>

<!-- Remove external ratings display -->
<?php
// DO NOT display:
// - Yelp ratings or review counts
// - Google ratings
// - Any external platform ratings
?>

<!-- Show only ZipPicks scores -->
<div class="zippicks-scores">
    <?php 
    $scores = get_post_meta(get_the_ID(), 'pillar_scores', true);
    if ($scores): 
    ?>
    <h3>ZipPicks Score</h3>
    <div class="score-grid">
        <?php foreach ($scores as $pillar => $score): ?>
        <div class="score-item">
            <span class="pillar"><?php echo esc_html(ucwords(str_replace('_', ' ', $pillar))); ?></span>
            <span class="score"><?php echo number_format($score, 1); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
```

#### B. Business Archive Updates

Update archive display to show verification status:

```php
// In archive-zippicks_business.php or business card template

<div class="business-card <?php echo $is_verified ? 'verified' : ''; ?>">
    <?php if ($is_verified): ?>
    <span class="verified-indicator" title="Verified Business">✓</span>
    <?php endif; ?>
    
    <!-- Business info -->
    
    <!-- Show top 3 vibes inline -->
    <?php if (!empty($api_vibes)): ?>
    <div class="quick-vibes">
        <?php foreach (array_slice($api_vibes, 0, 3) as $vibe): ?>
        <span class="mini-vibe"><?php echo esc_html($vibe['display_name']); ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
```

### 4. Admin Interface Enhancements

#### A. Business Edit Screen

Add API data metabox:

```php
class ZipPicks_Business_API_Metabox {
    
    public function add_metabox() {
        add_meta_box(
            'zippicks_api_data',
            'ZipBusiness API Data',
            [$this, 'render_metabox'],
            'zippicks_business',
            'side',
            'high'
        );
    }
    
    public function render_metabox($post) {
        $zpid = get_post_meta($post->ID, 'zpid', true);
        $verified = get_post_meta($post->ID, 'api_verified', true);
        $last_sync = get_post_meta($post->ID, 'last_api_sync', true);
        ?>
        <div class="api-data-metabox">
            <?php if ($zpid): ?>
                <p><strong>ZPID:</strong> <?php echo esc_html($zpid); ?></p>
                <p><strong>Status:</strong> 
                    <span class="<?php echo $verified ? 'verified' : 'unverified'; ?>">
                        <?php echo $verified ? 'Verified' : 'Unverified'; ?>
                    </span>
                </p>
                <?php if ($last_sync): ?>
                <p><strong>Last Sync:</strong> <?php echo esc_html(human_time_diff(strtotime($last_sync))); ?> ago</p>
                <?php endif; ?>
                <button class="button sync-api-data" data-zpid="<?php echo esc_attr($zpid); ?>">
                    Sync with API
                </button>
            <?php else: ?>
                <p>No API data linked</p>
                <input type="text" id="manual-zpid" placeholder="Enter ZPID">
                <button class="button link-zpid">Link ZPID</button>
            <?php endif; ?>
        </div>
        <?php
    }
}
```

#### B. Bulk Actions

Add bulk API operations:

```php
// In business admin class

public function add_bulk_actions($actions) {
    $actions['verify_api'] = 'Verify with API';
    $actions['sync_api'] = 'Sync API Data';
    $actions['enrich_api'] = 'Enrich from API';
    return $actions;
}

public function handle_bulk_actions($redirect_to, $doaction, $post_ids) {
    if ($doaction === 'verify_api') {
        foreach ($post_ids as $post_id) {
            $this->verify_business_with_api($post_id);
        }
    }
    return $redirect_to;
}
```

### 5. API Sync Service

Create service for syncing with ZipBusiness API:

```php
// New file: includes/class-api-sync-service.php

class ZipPicks_Business_API_Sync_Service {
    
    /**
     * Sync business with API data
     */
    public function sync_business($post_id, $zpid = null) {
        // Get zpid if not provided
        if (!$zpid) {
            $zpid = get_post_meta($post_id, 'zpid', true);
        }
        
        if (!$zpid) {
            return new WP_Error('no_zpid', 'No ZPID found');
        }
        
        // Fetch from API
        $api_data = $this->fetch_from_api($zpid);
        
        if (!$api_data) {
            return new WP_Error('api_error', 'Could not fetch API data');
        }
        
        // Update post meta
        update_post_meta($post_id, 'api_verified', true);
        update_post_meta($post_id, 'api_confidence_score', $api_data['confidence_score']);
        update_post_meta($post_id, 'api_enriched_data', json_encode($api_data));
        update_post_meta($post_id, 'last_api_sync', current_time('mysql'));
        
        // Update vibes
        $this->sync_vibes($post_id, $api_data['vibe_attributes']);
        
        // Update basic fields
        $this->update_business_fields($post_id, $api_data);
        
        return true;
    }
    
    /**
     * Sync vibe associations
     */
    private function sync_vibes($post_id, $api_vibes) {
        global $wpdb;
        
        // Clear existing API vibes
        $wpdb->delete(
            $wpdb->prefix . 'zippicks_business_vibes',
            ['business_id' => $post_id, 'source' => 'api']
        );
        
        // Insert new vibes
        foreach ($api_vibes as $vibe_slug => $confidence) {
            $wpdb->insert(
                $wpdb->prefix . 'zippicks_business_vibes',
                [
                    'business_id' => $post_id,
                    'vibe_slug' => $vibe_slug,
                    'confidence_score' => $confidence,
                    'source' => 'api'
                ]
            );
        }
        
        // Cache for display
        update_post_meta($post_id, 'api_vibes', json_encode($api_vibes));
    }
}
```

### 6. REST API Extensions

Add endpoints for API operations:

```php
// In REST controller

public function register_routes() {
    register_rest_route('zippicks/v1', '/business/(?P<id>\d+)/verify', [
        'methods' => 'POST',
        'callback' => [$this, 'verify_business'],
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ]);
    
    register_rest_route('zippicks/v1', '/business/(?P<id>\d+)/vibes', [
        'methods' => 'GET',
        'callback' => [$this, 'get_business_vibes'],
        'permission_callback' => '__return_true'
    ]);
}

public function get_business_vibes($request) {
    $post_id = $request['id'];
    
    global $wpdb;
    $vibes = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}zippicks_business_vibes 
         WHERE business_id = %d 
         ORDER BY confidence_score DESC",
        $post_id
    ));
    
    return rest_ensure_response([
        'vibes' => $vibes,
        'verified' => get_post_meta($post_id, 'api_verified', true)
    ]);
}
```

### 7. Frontend JavaScript Updates

```javascript
// In business admin JS

jQuery(document).ready(function($) {
    // Sync API data button
    $('.sync-api-data').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const zpid = button.data('zpid');
        const postId = $('#post_ID').val();
        
        button.prop('disabled', true).text('Syncing...');
        
        wp.ajax.post('zippicks_sync_api_data', {
            post_id: postId,
            zpid: zpid,
            nonce: zippicks_business.nonce
        })
        .done(function(response) {
            button.text('Synced!');
            location.reload(); // Refresh to show new data
        })
        .fail(function(error) {
            alert('Sync failed: ' + error);
            button.prop('disabled', false).text('Sync with API');
        });
    });
});
```

### 8. Schema.org Updates

Update structured data to include verification:

```php
// In schema generation

if ($is_verified) {
    $schema['identifier'] = [
        '@type' => 'PropertyValue',
        'propertyID' => 'ZipBusinessID',
        'value' => $zpid
    ];
    
    $schema['additionalProperty'] = [
        '@type' => 'PropertyValue',
        'name' => 'Verification Status',
        'value' => 'Verified by ZipBusiness'
    ];
}
```

### 9. Performance Optimizations

```php
// Batch loading for archive pages
class ZipPicks_Business_Batch_Loader {
    
    public function preload_api_data($post_ids) {
        $zpids = [];
        foreach ($post_ids as $post_id) {
            $zpid = get_post_meta($post_id, 'zpid', true);
            if ($zpid) {
                $zpids[$post_id] = $zpid;
            }
        }
        
        // Single query for all vibes
        global $wpdb;
        $vibes = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}zippicks_business_vibes 
             WHERE business_id IN (" . implode(',', array_keys($zpids)) . ")"
        );
        
        // Group by business
        $grouped = [];
        foreach ($vibes as $vibe) {
            $grouped[$vibe->business_id][] = $vibe;
        }
        
        // Cache in memory for this request
        wp_cache_set('business_vibes_batch', $grouped, 'zippicks', 300);
    }
}
```

### 10. Migration Plan

1. **Phase 1**: Deploy schema updates
   - Add new post meta fields
   - Create vibes table
   - Update ACF configurations

2. **Phase 2**: Backend integration
   - Deploy sync service
   - Add admin UI elements
   - Enable API endpoints

3. **Phase 3**: Frontend updates
   - Update templates
   - Remove external ratings
   - Add verification badges

4. **Phase 4**: Data migration
   - Backfill zpids where possible
   - Run initial sync for existing businesses
   - Verify data integrity

### 11. Testing Checklist

- [ ] Create business with ZPID
- [ ] Verify API sync works
- [ ] Check vibe display
- [ ] Confirm no external ratings shown
- [ ] Test bulk operations
- [ ] Verify schema.org output
- [ ] Check performance with 100+ businesses
- [ ] Test verification badge display

### 12. Rollback Procedures

If issues occur:
1. Disable API sync features via feature flag
2. Hide verification badges
3. Restore previous templates
4. Investigate issues
5. Re-deploy with fixes

## Success Metrics

- All Master Critic businesses have zpids
- Verification badges display correctly
- Vibes show with proper confidence scores
- No external ratings visible
- Page load time < 2s with API data

## Questions for Product

1. Show confidence scores to users?
2. Allow manual vibe additions?
3. Display enrichment timestamp?
4. Premium feature: Live sync?
5. Vibe filtering on archive pages?