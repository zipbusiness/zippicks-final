# ZipPicks Social - Engineering Handoff for Remaining Phases

## ⚠️ CRITICAL: THEATRICAL CODE REMOVAL REQUIRED

Per CLAUDE.md enterprise standards, the following stub/placeholder code MUST be replaced with actual working implementations. **NO THEATRICAL CODE IS ALLOWED IN PRODUCTION.**

### Current Theatrical Code That Must Be Replaced:

1. **Activity Feed Template** (`templates/activity-feed.php`)
   - Lines 20-34: Placeholder divs with "Loading activity feed..."
   - **REQUIRED**: Implement actual feed loading from `wp_zippicks_activities` table

2. **Follow Suggestions Endpoint** (`api/class-rest-controller.php`)
   - Lines 295-302: Returns empty array
   - **REQUIRED**: Implement suggestion algorithm based on mutual follows, location, and behavior

3. **Activity Feed Endpoint** (`api/class-rest-controller.php`)
   - Lines 313-322: Returns empty array
   - **REQUIRED**: Query and return actual activities from followed entities

4. **Activity Feed JavaScript** (`assets/js/follow-system.js`)
   - Lines 381-395: Fake timeout showing empty state
   - **REQUIRED**: Implement actual AJAX feed loading

5. **Notifications System**
   - Currently no implementation beyond database schema
   - **REQUIRED**: Full notification delivery system

## Phase 2: Enhanced Features Implementation

### 1. Activity Stream (PRIORITY: HIGH)

**Database Table Already Exists**: `wp_zippicks_activities`

**Required Implementation**:

```php
// In class-activity-stream.php (NEW FILE REQUIRED)
class ZipPicks_Social_Activity_Stream {
    
    /**
     * Record an activity
     * MUST ACTUALLY WRITE TO DATABASE
     */
    public function record_activity($actor_id, $action, $object_type, $object_id, $metadata = []) {
        global $wpdb;
        
        // REQUIRED: Insert into activities table
        // REQUIRED: Trigger notification system
        // REQUIRED: Update cache
    }
    
    /**
     * Get activity feed for user
     * MUST RETURN REAL DATA
     */
    public function get_feed($user_id, $args = []) {
        // REQUIRED: Query activities from followed entities
        // REQUIRED: Apply privacy filters
        // REQUIRED: Format for display
        // REQUIRED: Implement pagination
    }
}
```

**Integration Points**:
- Hook into `save_post` for new reviews/lists
- Hook into `zippicks_social_after_follow` for follow activities
- Hook into comment system for engagement
- Hook into favorites system when implemented

**Frontend Requirements**:
- Replace placeholder template with actual item rendering
- Implement infinite scroll
- Add real-time updates via AJAX polling (WebSocket in Phase 4)
- Show rich previews of content

### 2. Follow Suggestions Engine (PRIORITY: HIGH)

**Database Table Already Exists**: `wp_zippicks_follow_suggestions`

**Required Algorithm Implementation**:

```php
// In class-suggestions.php (NEW FILE REQUIRED)
class ZipPicks_Social_Suggestions_Engine {
    
    /**
     * Generate suggestions for user
     * MUST USE ACTUAL DATA AND ALGORITHMS
     */
    public function generate_suggestions($user_id) {
        // REQUIRED ALGORITHMS:
        
        // 1. Mutual Follows
        // - Find users who follow people you follow
        // - Weight by number of mutual connections
        
        // 2. Location Based
        // - Users in same city/area
        // - Businesses near user's activity
        
        // 3. Taste Similarity
        // - Users who reviewed same businesses
        // - Similar rating patterns
        
        // 4. Trending
        // - Recently active users
        // - High engagement entities
        
        // MUST: Store in suggestions table with scores
        // MUST: Implement dismissal tracking
    }
}
```

**Cron Job Required**:
```php
// Daily suggestion generation
add_action('zippicks_social_generate_suggestions', function() {
    // Process users in batches
    // Calculate suggestion scores
    // Update suggestions table
});
```

### 3. Email Notifications (PRIORITY: MEDIUM)

**Required Implementation**:

```php
// In class-notifications.php (UPDATE REQUIRED)
class ZipPicks_Social_Notifications {
    
    /**
     * Send new follower notification
     * MUST ACTUALLY SEND EMAILS
     */
    public function notify_new_follower($followed_id, $follower_id) {
        // REQUIRED: Check user preferences
        // REQUIRED: Generate email content
        // REQUIRED: Use wp_mail() or service
        // REQUIRED: Log delivery status
    }
    
    /**
     * Send digest emails
     * MUST PROCESS AND SEND REAL DIGESTS
     */
    public function send_digest_emails() {
        // REQUIRED: Query users with digest enabled
        // REQUIRED: Aggregate weekly activities
        // REQUIRED: Generate personalized content
        // REQUIRED: Batch send emails
    }
}
```

**Email Templates Required**:
- New follower notification
- Weekly digest
- Milestone notifications (100 followers, etc.)
- Re-engagement emails

## Phase 3: Advanced Features Implementation

### 1. Privacy Controls (PRIORITY: HIGH)

**Required Database Changes**:
```sql
-- Add privacy settings table
CREATE TABLE wp_zippicks_privacy_settings (
    user_id BIGINT(20) UNSIGNED PRIMARY KEY,
    account_private BOOLEAN DEFAULT FALSE,
    approve_followers BOOLEAN DEFAULT FALSE,
    hide_following_list BOOLEAN DEFAULT FALSE,
    hide_followers_list BOOLEAN DEFAULT FALSE,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Add block list table  
CREATE TABLE wp_zippicks_blocks (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blocker_id BIGINT(20) UNSIGNED NOT NULL,
    blocked_id BIGINT(20) UNSIGNED NOT NULL,
    blocked_type ENUM('user', 'critic') DEFAULT 'user',
    reason VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_block (blocker_id, blocked_id, blocked_type)
);
```

**Required Implementation**:
```php
// In class-privacy-manager.php (NEW FILE REQUIRED)
class ZipPicks_Social_Privacy_Manager {
    
    /**
     * Block a user
     * MUST ENFORCE THROUGHOUT SYSTEM
     */
    public function block_user($blocker_id, $blocked_id) {
        // REQUIRED: Add to blocks table
        // REQUIRED: Remove any existing follows
        // REQUIRED: Prevent future interactions
        // REQUIRED: Hide content from blocked user
    }
    
    /**
     * Check if user can view content
     * MUST BE CALLED BEFORE DISPLAYING DATA
     */
    public function can_view($viewer_id, $content_owner_id) {
        // REQUIRED: Check block status
        // REQUIRED: Check privacy settings
        // REQUIRED: Check follow approval status
    }
}
```

### 2. Bulk Follow Management (PRIORITY: MEDIUM)

**Required UI Components**:
- Checkbox selection on lists
- "Follow All" / "Unfollow All" buttons
- Import followers from other platforms
- Export following list

**Required Implementation**:
```php
public function bulk_follow($follower_id, $entity_ids, $entity_type) {
    // REQUIRED: Validate all entities exist
    // REQUIRED: Check rate limits (higher for bulk)
    // REQUIRED: Process in transaction
    // REQUIRED: Send batch notifications
    // REQUIRED: Update stats efficiently
}
```

### 3. Enhanced Analytics (PRIORITY: LOW)

**Required Metrics**:
- Follow growth over time (chart)
- Engagement rates
- Most engaged followers
- Follow source tracking
- Churn analysis

**Required Implementation**:
```php
// In class-analytics.php (UPDATE REQUIRED)
class ZipPicks_Social_Analytics {
    
    /**
     * Track follow source
     * MUST RECORD WHERE FOLLOWS ORIGINATE
     */
    public function track_follow_source($follow_id, $source) {
        // Sources: profile, list, suggestion, search
        // Store in meta table
        // Generate reports
    }
}
```

## Phase 4: Scale & Optimization

### 1. Real-time Updates (PRIORITY: MEDIUM)

**WebSocket Implementation Required**:
```javascript
// In follow-system-realtime.js (NEW FILE REQUIRED)
class ZipPicksRealtime {
    constructor() {
        // REQUIRED: WebSocket connection
        // REQUIRED: Authentication
        // REQUIRED: Reconnection logic
        // REQUIRED: Event handling
    }
    
    subscribeToUser(userId) {
        // Real-time follow count updates
        // Activity stream updates
        // Notification delivery
    }
}
```

### 2. Redis Caching Layer (PRIORITY: HIGH)

**Required Cache Updates**:
```php
// In class-cache-manager.php (UPDATE REQUIRED)
public function get($key, $group) {
    // REQUIRED: Try Redis first
    // REQUIRED: Fall back to transients
    // REQUIRED: Implement cache warming
}
```

### 3. Database Optimization (PRIORITY: HIGH)

**Required Indexes**:
```sql
-- Add covering indexes for common queries
CREATE INDEX idx_follow_stats_lookup 
ON wp_zippicks_follows (follower_id, followed_type, status, created_at);

-- Partition activities table by month
ALTER TABLE wp_zippicks_activities 
PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at));
```

## Testing Requirements

### Required Test Coverage:

1. **Unit Tests** (0% → 80% coverage required)
   - Follow/unfollow logic
   - Permission checks
   - Rate limiting
   - Cache operations

2. **Integration Tests**
   - Database operations
   - API endpoints
   - Email delivery
   - Activity recording

3. **Performance Tests**
   - 10K concurrent follows
   - 1M+ follower accounts
   - Feed generation speed
   - Cache effectiveness

## Deployment Checklist

### Before Each Phase Launch:

- [ ] Remove ALL placeholder returns
- [ ] Remove ALL setTimeout fake delays  
- [ ] Remove ALL empty implementations
- [ ] Implement actual database queries
- [ ] Add proper error handling
- [ ] Test with production data volumes
- [ ] Verify sub-100ms response times
- [ ] Security audit completed
- [ ] Load testing passed
- [ ] Documentation updated

## Migration Scripts Required

```php
// migration-phase-2.php
function migrate_to_phase_2() {
    // Add notification preferences to existing users
    // Populate initial suggestions
    // Backfill activity data
}

// migration-phase-3.php  
function migrate_to_phase_3() {
    // Create privacy tables
    // Set default privacy settings
    // Migrate muted users to new system
}
```

## Performance Benchmarks

**Required Performance Targets**:
- Follow action: <100ms
- Feed generation: <200ms for 100 items
- Suggestion calculation: <5s per user batch
- Notification delivery: <30s from trigger
- Cache hit rate: >90%

## Security Audit Requirements

- [ ] SQL injection prevention verified
- [ ] XSS prevention on all outputs
- [ ] CSRF tokens on all state changes
- [ ] Rate limiting cannot be bypassed
- [ ] Private account data not exposed
- [ ] Block enforcement complete

## CRITICAL REMINDERS

1. **NO PLACEHOLDER CODE** - Every function must work
2. **NO FAKE DELAYS** - Real processing only
3. **NO EMPTY RETURNS** - Return real data or throw errors
4. **NO "COMING SOON"** - Hidden until complete
5. **NO PARTIAL FEATURES** - 100% working or not shipped

**Per CLAUDE.md**: "Code either works or it doesn't; there's no 'mostly works'"

## Support & Questions

Document all architectural decisions in `/docs/`
Create runbooks for common operations
Set up monitoring before launch
Plan for 10x growth from day one