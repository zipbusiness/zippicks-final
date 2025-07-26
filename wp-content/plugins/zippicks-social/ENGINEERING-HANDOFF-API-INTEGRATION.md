# ZipPicks Social Plugin - API Integration Engineering Handoff

## Overview

The ZipPicks Social plugin has been built with full API integration in mind. The WordPress side is complete and waiting for the PostgreSQL backend implementation. This document outlines exactly what needs to be built on the API side.

## Architecture Summary

```
WordPress Frontend (COMPLETE)          ZipBusiness API (TODO)
├── API Client Class                   ├── PostgreSQL Tables
├── Follow Manager                     ├── FastAPI Endpoints
├── Activity Stream                    ├── Social Services
├── Suggestions Engine                 └── Taste Graph Integration
└── Email Notifications
```

## 1. PostgreSQL Tables Required

### 1.1 Core Social Tables

```sql
-- User follows table (core social relationships)
CREATE TABLE user_follows (
    id BIGSERIAL PRIMARY KEY,
    follower_id BIGINT NOT NULL,
    followed_id BIGINT NOT NULL,
    followed_type VARCHAR(20) NOT NULL CHECK (followed_type IN ('user', 'critic', 'business', 'list')),
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'pending', 'blocked', 'muted')),
    notification_pref VARCHAR(20) DEFAULT 'all' CHECK (notification_pref IN ('all', 'important', 'none')),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(follower_id, followed_id, followed_type),
    INDEX idx_follower (follower_id, status),
    INDEX idx_followed (followed_id, followed_type, status),
    INDEX idx_created_at (created_at)
);

-- Social activities table
CREATE TABLE social_activities (
    id BIGSERIAL PRIMARY KEY,
    actor_id BIGINT NOT NULL,
    actor_type VARCHAR(20) DEFAULT 'user' CHECK (actor_type IN ('user', 'critic', 'business')),
    action VARCHAR(50) NOT NULL,
    object_type VARCHAR(50),
    object_id BIGINT,
    metadata JSONB,
    visibility VARCHAR(20) DEFAULT 'public' CHECK (visibility IN ('public', 'followers', 'private')),
    created_at TIMESTAMP DEFAULT NOW(),
    INDEX idx_actor (actor_id, actor_type, created_at DESC),
    INDEX idx_visibility (visibility, created_at DESC),
    INDEX idx_action (action, created_at DESC)
);

-- Follow suggestions table
CREATE TABLE follow_suggestions (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    suggested_id BIGINT NOT NULL,
    suggested_type VARCHAR(20) NOT NULL CHECK (suggested_type IN ('user', 'critic', 'business')),
    reason VARCHAR(100),
    score DECIMAL(5,2) DEFAULT 0,
    shown_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    dismissed_at TIMESTAMP NULL,
    UNIQUE(user_id, suggested_id, suggested_type),
    INDEX idx_user_score (user_id, dismissed_at, score DESC),
    INDEX idx_reason (reason, created_at DESC)
);

-- Follow statistics cache table
CREATE TABLE follow_stats_cache (
    entity_id BIGINT NOT NULL,
    entity_type VARCHAR(20) NOT NULL,
    followers_count INT DEFAULT 0,
    following_count INT DEFAULT 0,
    mutual_count INT DEFAULT 0,
    last_calculated TIMESTAMP DEFAULT NOW(),
    PRIMARY KEY (entity_id, entity_type),
    INDEX idx_type_followers (entity_type, followers_count DESC),
    INDEX idx_last_calc (last_calculated)
);

-- Privacy settings table
CREATE TABLE user_privacy_settings (
    user_id BIGINT PRIMARY KEY,
    account_private BOOLEAN DEFAULT FALSE,
    approve_followers BOOLEAN DEFAULT FALSE,
    hide_following_list BOOLEAN DEFAULT FALSE,
    hide_followers_list BOOLEAN DEFAULT FALSE,
    activity_visibility VARCHAR(20) DEFAULT 'public',
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Block list table
CREATE TABLE user_blocks (
    id BIGSERIAL PRIMARY KEY,
    blocker_id BIGINT NOT NULL,
    blocked_id BIGINT NOT NULL,
    blocked_type VARCHAR(20) DEFAULT 'user',
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(blocker_id, blocked_id, blocked_type),
    INDEX idx_blocker (blocker_id),
    INDEX idx_blocked (blocked_id, blocked_type)
);

-- Notification preferences table
CREATE TABLE notification_preferences (
    user_id BIGINT PRIMARY KEY,
    new_follower BOOLEAN DEFAULT TRUE,
    follow_request BOOLEAN DEFAULT TRUE,
    milestone_reached BOOLEAN DEFAULT TRUE,
    weekly_digest BOOLEAN DEFAULT TRUE,
    recommendations BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Email notification queue
CREATE TABLE email_notification_queue (
    id BIGSERIAL PRIMARY KEY,
    recipient_id BIGINT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    template_data JSONB NOT NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'sent', 'failed')),
    scheduled_for TIMESTAMP DEFAULT NOW(),
    sent_at TIMESTAMP,
    error_message TEXT,
    retry_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    INDEX idx_status_scheduled (status, scheduled_for),
    INDEX idx_recipient (recipient_id, status)
);
```

### 1.2 Integration Tables (Link to Existing Tables)

```sql
-- Add social features to existing WordPress users table
ALTER TABLE wordpress_users ADD COLUMN IF NOT EXISTS 
    follower_count INT DEFAULT 0,
    following_count INT DEFAULT 0,
    is_verified BOOLEAN DEFAULT FALSE,
    social_bio TEXT,
    social_metadata JSONB;

-- Link social activities to restaurants
ALTER TABLE restaurants ADD COLUMN IF NOT EXISTS
    follower_count INT DEFAULT 0,
    last_activity_at TIMESTAMP;

-- Add indexes for social queries
CREATE INDEX IF NOT EXISTS idx_wp_users_social ON wordpress_users(follower_count DESC, is_verified);
CREATE INDEX IF NOT EXISTS idx_restaurants_social ON restaurants(follower_count DESC);
```

### 1.3 Materialized Views for Performance

```sql
-- Trending users view (refresh hourly)
CREATE MATERIALIZED VIEW trending_users AS
SELECT 
    u.wp_user_id,
    u.display_name,
    COUNT(DISTINCT f.follower_id) as new_followers_week,
    u.follower_count,
    (COUNT(DISTINCT f.follower_id)::FLOAT / NULLIF(u.follower_count, 1)) as growth_rate
FROM wordpress_users u
JOIN user_follows f ON f.followed_id = u.wp_user_id 
    AND f.followed_type = 'user'
    AND f.created_at > NOW() - INTERVAL '7 days'
GROUP BY u.wp_user_id, u.display_name, u.follower_count
HAVING COUNT(DISTINCT f.follower_id) > 5
ORDER BY growth_rate DESC
LIMIT 100;

CREATE INDEX idx_trending_users ON trending_users(wp_user_id);

-- User taste overlap view (refresh daily)
CREATE MATERIALIZED VIEW user_taste_overlaps AS
SELECT 
    a.user_id as user_a,
    b.user_id as user_b,
    COUNT(DISTINCT a.vibe_id) as common_vibes,
    ARRAY_AGG(DISTINCT v.vibe_name ORDER BY v.vibe_name) as shared_vibes
FROM wp_user_vibe_interactions a
JOIN wp_user_vibe_interactions b ON a.vibe_id = b.vibe_id
JOIN vibe_definitions v ON v.id = a.vibe_id
WHERE a.user_id < b.user_id
    AND a.interaction_type IN ('favorite', 'search', 'view')
    AND b.interaction_type IN ('favorite', 'search', 'view')
GROUP BY a.user_id, b.user_id
HAVING COUNT(DISTINCT a.vibe_id) >= 3;

CREATE INDEX idx_taste_overlap_users ON user_taste_overlaps(user_a, user_b);
```

## 2. API Endpoints Required

### 2.1 Follow System Endpoints

```python
# In app/api/endpoints/social.py

@router.post("/api/v1/social/follow")
async def follow_entity(
    follower_id: int,
    followed_id: int,
    followed_type: str,
    db: Session = Depends(get_db)
):
    """Follow a user, critic, or business"""
    # 1. Validate entities exist
    # 2. Check not already following
    # 3. Check privacy settings (if user)
    # 4. Create follow record
    # 5. Update stats cache
    # 6. Record activity
    # 7. Queue notification
    
@router.post("/api/v1/social/unfollow")
async def unfollow_entity(
    follower_id: int,
    followed_id: int,
    followed_type: str,
    db: Session = Depends(get_db)
):
    """Unfollow an entity"""
    # 1. Validate follow exists
    # 2. Delete follow record
    # 3. Update stats cache
    # 4. Record activity

@router.get("/api/v1/social/is-following")
async def check_following(
    follower_id: int,
    followed_id: int,
    followed_type: str,
    db: Session = Depends(get_db)
):
    """Check if following an entity"""
    # Return is_following: bool

@router.get("/api/v1/social/followers")
async def get_followers(
    entity_id: int,
    entity_type: str,
    limit: int = 20,
    offset: int = 0,
    db: Session = Depends(get_db)
):
    """Get followers of an entity"""
    # 1. Check privacy settings
    # 2. Return paginated followers with user details

@router.get("/api/v1/social/following")
async def get_following(
    user_id: int,
    entity_type: Optional[str] = None,
    limit: int = 20,
    offset: int = 0,
    db: Session = Depends(get_db)
):
    """Get entities a user follows"""
    # 1. Check privacy settings
    # 2. Return paginated following list

@router.get("/api/v1/social/stats/{entity_type}/{entity_id}")
async def get_follow_stats(
    entity_type: str,
    entity_id: int,
    db: Session = Depends(get_db)
):
    """Get follow statistics"""
    # Return followers_count, following_count, mutual_count
```

### 2.2 Activity Feed Endpoints

```python
@router.get("/api/v1/social/activity-feed")
async def get_activity_feed(
    user_id: int,
    limit: int = 20,
    offset: int = 0,
    include_self: bool = True,
    db: Session = Depends(get_db)
):
    """Get personalized activity feed"""
    # 1. Get users/entities the user follows
    # 2. Fetch recent activities from followed entities
    # 3. Apply privacy filters
    # 4. Return formatted activities

@router.post("/api/v1/social/activities")
async def record_activity(
    activity: ActivityCreate,
    db: Session = Depends(get_db)
):
    """Record a new activity"""
    # 1. Validate activity data
    # 2. Insert into activities table
    # 3. Update last_activity_at timestamps
```

### 2.3 Suggestions Endpoints

```python
@router.get("/api/v1/social/suggestions")
async def get_follow_suggestions(
    user_id: int,
    limit: int = 10,
    type: str = "all",
    db: Session = Depends(get_db)
):
    """Get personalized follow suggestions"""
    # Check if cached suggestions exist
    # If not, generate new suggestions (see algorithm below)

@router.post("/api/v1/social/suggestions/dismiss")
async def dismiss_suggestion(
    user_id: int,
    suggested_id: int,
    suggested_type: str,
    db: Session = Depends(get_db)
):
    """Dismiss a follow suggestion"""
    # Update dismissed_at timestamp

@router.get("/api/v1/social/taste-similar-users")
async def get_taste_similar_users(
    user_id: int,
    limit: int = 20,
    db: Session = Depends(get_db)
):
    """Get users with similar taste profiles"""
    # Query user_taste_overlaps view
    # Join with user details
    # Return sorted by overlap score

@router.get("/api/v1/social/mutual-connections")
async def get_mutual_connections(
    user_id: int,
    target_id: int,
    target_type: str,
    db: Session = Depends(get_db)
):
    """Get mutual connections between users"""
```

### 2.4 Privacy & Blocking Endpoints

```python
@router.post("/api/v1/social/block")
async def block_user(
    blocker_id: int,
    blocked_id: int,
    reason: Optional[str] = None,
    db: Session = Depends(get_db)
):
    """Block a user"""
    # 1. Create block record
    # 2. Remove any existing follows
    # 3. Hide content bidirectionally

@router.put("/api/v1/social/privacy/{user_id}")
async def update_privacy_settings(
    user_id: int,
    settings: PrivacySettingsUpdate,
    db: Session = Depends(get_db)
):
    """Update user privacy settings"""
```

### 2.5 Bulk Operations

```python
@router.post("/api/v1/social/bulk-follow")
async def bulk_follow(
    follower_id: int,
    entities: List[EntityToFollow],
    db: Session = Depends(get_db)
):
    """Follow multiple entities at once"""
    # Process in transaction
    # Return success/failure for each
```

## 3. Suggestion Algorithm Implementation

```python
# In app/services/social/suggestions.py

class SuggestionEngine:
    
    def generate_suggestions(self, user_id: int, db: Session) -> List[Suggestion]:
        suggestions = []
        
        # 1. Mutual Follows (Weight: 30%)
        mutual_suggestions = self._get_mutual_follow_suggestions(user_id, db)
        
        # 2. Taste Similarity (Weight: 35%)
        taste_suggestions = self._get_taste_based_suggestions(user_id, db)
        
        # 3. Location Based (Weight: 15%)
        location_suggestions = self._get_location_suggestions(user_id, db)
        
        # 4. Trending (Weight: 10%)
        trending_suggestions = self._get_trending_suggestions(user_id, db)
        
        # 5. Activity Level (Weight: 10%)
        # Boost users who are active
        
        # Combine and score
        all_suggestions = self._combine_and_score(
            mutual_suggestions,
            taste_suggestions,
            location_suggestions,
            trending_suggestions
        )
        
        # Remove already following and dismissed
        filtered = self._filter_suggestions(all_suggestions, user_id, db)
        
        # Store top suggestions
        self._store_suggestions(user_id, filtered[:50], db)
        
        return filtered[:10]
    
    def _get_taste_based_suggestions(self, user_id: int, db: Session):
        """Find users with similar taste using vibe overlaps"""
        query = """
        SELECT 
            uto.user_b as suggested_id,
            'user' as suggested_type,
            uto.common_vibes,
            uto.shared_vibes,
            0.35 * (uto.common_vibes::float / 10) as score
        FROM user_taste_overlaps uto
        WHERE uto.user_a = :user_id
            AND NOT EXISTS (
                SELECT 1 FROM user_follows uf
                WHERE uf.follower_id = :user_id
                    AND uf.followed_id = uto.user_b
                    AND uf.followed_type = 'user'
            )
        ORDER BY uto.common_vibes DESC
        LIMIT 20
        """
        return db.execute(query, {"user_id": user_id}).fetchall()
```

## 4. Activity Recording Integration

```python
# In app/services/social/activities.py

class ActivityRecorder:
    
    async def record_follow_activity(self, follower_id: int, followed_id: int, followed_type: str):
        """Record when someone follows an entity"""
        activity = {
            "actor_id": follower_id,
            "actor_type": "user",
            "action": f"follow_{followed_type}",
            "object_type": followed_type,
            "object_id": followed_id,
            "visibility": "public"
        }
        await self.record_activity(activity)
    
    async def record_favorite_activity(self, user_id: int, restaurant_zpid: str):
        """Record when someone favorites a restaurant"""
        # Get restaurant ID from ZPID
        restaurant = await self.get_restaurant_by_zpid(restaurant_zpid)
        if restaurant:
            activity = {
                "actor_id": user_id,
                "actor_type": "user", 
                "action": "favorite_restaurant",
                "object_type": "restaurant",
                "object_id": restaurant.id,
                "metadata": {
                    "restaurant_name": restaurant.name,
                    "vibes": restaurant.vibes[:3]
                }
            }
            await self.record_activity(activity)
```

## 5. Email Notification Implementation

```python
# In app/services/social/notifications.py

class NotificationService:
    
    async def queue_new_follower_notification(self, followed_id: int, follower_id: int):
        """Queue email notification for new follower"""
        # Check user preferences
        prefs = await self.get_notification_preferences(followed_id)
        if not prefs.new_follower:
            return
            
        # Get user details
        follower = await self.get_user(follower_id)
        followed = await self.get_user(followed_id)
        
        # Queue email
        notification = {
            "recipient_id": followed_id,
            "notification_type": "new_follower",
            "subject": f"{follower.display_name} started following you",
            "template_data": {
                "follower_name": follower.display_name,
                "follower_avatar": follower.avatar_url,
                "follower_bio": follower.social_bio,
                "follower_stats": await self.get_user_stats(follower_id),
                "action_url": f"https://zippicks.com/user/{follower_id}"
            }
        }
        await self.queue_notification(notification)
```

## 6. Cron Jobs Required

```python
# In app/cron/social_tasks.py

@celery.task
def generate_follow_suggestions():
    """Run every 12 hours"""
    # Generate suggestions for active users
    
@celery.task
def send_weekly_digests():
    """Run every Monday at 10am"""
    # Send activity digest emails
    
@celery.task
def update_follow_stats():
    """Run every hour"""
    # Update follow_stats_cache table
    
@celery.task
def refresh_trending_users():
    """Run every hour"""
    # Refresh materialized view
    
@celery.task
def cleanup_old_activities():
    """Run daily"""
    # Delete activities older than 90 days
```

## 7. WordPress Plugin Integration Points

The WordPress plugin expects these response formats:

### Follow Response
```json
{
    "success": true,
    "follow_id": 12345,
    "message": "Successfully followed"
}
```

### Activity Feed Response
```json
{
    "data": [
        {
            "id": 1,
            "actor_id": 123,
            "actor_type": "user",
            "action": "follow_user",
            "object_type": "user",
            "object_id": 456,
            "metadata": {},
            "created_at": "2024-01-20T10:30:00Z"
        }
    ],
    "total": 150,
    "has_more": true
}
```

### Suggestions Response
```json
{
    "data": [
        {
            "suggested_id": 789,
            "suggested_type": "user",
            "reason": "taste_similarity",
            "score": 0.85,
            "reason_data": {
                "common_vibes": ["Natural Wine", "Farm to Table"],
                "overlap_score": 0.75
            }
        }
    ]
}
```

## 8. Testing Requirements

### API Tests Required
```python
# tests/test_social_api.py

def test_follow_unfollow_cycle():
    """Test complete follow/unfollow flow"""
    
def test_privacy_settings_enforcement():
    """Ensure private accounts work correctly"""
    
def test_block_enforcement():
    """Verify blocks prevent all interactions"""
    
def test_suggestion_generation():
    """Test all suggestion algorithms"""
    
def test_activity_feed_performance():
    """Ensure feed loads in <200ms"""
```

### Load Tests
- 1000 concurrent follow operations
- Activity feed with 10k+ activities
- Suggestion generation for 1000 users

## 9. Monitoring & Analytics

Add these metrics to your monitoring:
- Follow/unfollow rate per minute
- Activity feed query time (p50, p95, p99)
- Suggestion click-through rate
- Email notification delivery rate
- API endpoint response times

## 10. Security Considerations

1. **Rate Limiting**: Implement per-user rate limits
2. **Privacy**: Always check privacy settings before returning data
3. **Blocking**: Enforce blocks bidirectionally
4. **Data Access**: Users can only modify their own follows
5. **Caching**: Clear caches on privacy changes

## Success Criteria

The social system is complete when:
- [ ] All PostgreSQL tables are created
- [ ] All API endpoints return real data
- [ ] Follow/unfollow works bidirectionally
- [ ] Activity feed shows real activities
- [ ] Suggestions use Taste Graph data
- [ ] Email notifications are sent
- [ ] Privacy settings are enforced
- [ ] Blocking prevents all interactions
- [ ] All tests pass
- [ ] Performance targets are met

## Questions to Address

1. **Email Service**: Should we use SendGrid, AWS SES, or rely on WordPress?
2. **WebSocket**: Do you want real-time updates now or in Phase 2?
3. **Rate Limits**: What are acceptable follow rates?
4. **Data Retention**: How long to keep activities?
5. **Caching Strategy**: Redis or PostgreSQL-based caching?

---

This completes the WordPress side of the social plugin. The API implementation will create a powerful, taste-driven social network that leverages your unique Taste Graph data moat.