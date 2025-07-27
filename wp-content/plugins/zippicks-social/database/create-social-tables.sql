-- =====================================================
-- ZipPicks Social Plugin - PostgreSQL Tables
-- =====================================================
-- Run this script in your ZipBusiness API PostgreSQL database
-- to create all required tables for the social features
-- =====================================================

-- 1. CORE SOCIAL TABLES
-- =====================================================

-- User follows table (core social relationships)
CREATE TABLE IF NOT EXISTS user_follows (
    id BIGSERIAL PRIMARY KEY,
    follower_id BIGINT NOT NULL,
    followed_id BIGINT NOT NULL,
    followed_type VARCHAR(20) NOT NULL CHECK (followed_type IN ('user', 'critic', 'business', 'list')),
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'pending', 'blocked', 'muted')),
    notification_pref VARCHAR(20) DEFAULT 'all' CHECK (notification_pref IN ('all', 'important', 'none')),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(follower_id, followed_id, followed_type)
);

-- Create indexes for follow queries
CREATE INDEX idx_user_follows_follower ON user_follows(follower_id, status);
CREATE INDEX idx_user_follows_followed ON user_follows(followed_id, followed_type, status);
CREATE INDEX idx_user_follows_created_at ON user_follows(created_at);

-- Social activities table
CREATE TABLE IF NOT EXISTS social_activities (
    id BIGSERIAL PRIMARY KEY,
    actor_id BIGINT NOT NULL,
    actor_type VARCHAR(20) DEFAULT 'user' CHECK (actor_type IN ('user', 'critic', 'business')),
    action VARCHAR(50) NOT NULL,
    object_type VARCHAR(50),
    object_id BIGINT,
    metadata JSONB,
    visibility VARCHAR(20) DEFAULT 'public' CHECK (visibility IN ('public', 'followers', 'private')),
    created_at TIMESTAMP DEFAULT NOW()
);

-- Create indexes for activity queries
CREATE INDEX idx_social_activities_actor ON social_activities(actor_id, actor_type, created_at DESC);
CREATE INDEX idx_social_activities_visibility ON social_activities(visibility, created_at DESC);
CREATE INDEX idx_social_activities_action ON social_activities(action, created_at DESC);

-- Follow suggestions table
CREATE TABLE IF NOT EXISTS follow_suggestions (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    suggested_id BIGINT NOT NULL,
    suggested_type VARCHAR(20) NOT NULL CHECK (suggested_type IN ('user', 'critic', 'business')),
    reason VARCHAR(100),
    score DECIMAL(5,2) DEFAULT 0,
    shown_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    dismissed_at TIMESTAMP NULL,
    UNIQUE(user_id, suggested_id, suggested_type)
);

-- Create indexes for suggestion queries
CREATE INDEX idx_follow_suggestions_user_score ON follow_suggestions(user_id, dismissed_at, score DESC);
CREATE INDEX idx_follow_suggestions_reason ON follow_suggestions(reason, created_at DESC);

-- Follow statistics cache table
CREATE TABLE IF NOT EXISTS follow_stats_cache (
    entity_id BIGINT NOT NULL,
    entity_type VARCHAR(20) NOT NULL,
    followers_count INT DEFAULT 0,
    following_count INT DEFAULT 0,
    mutual_count INT DEFAULT 0,
    last_calculated TIMESTAMP DEFAULT NOW(),
    PRIMARY KEY (entity_id, entity_type)
);

-- Create indexes for stats queries
CREATE INDEX idx_follow_stats_cache_type_followers ON follow_stats_cache(entity_type, followers_count DESC);
CREATE INDEX idx_follow_stats_cache_last_calc ON follow_stats_cache(last_calculated);

-- 2. PRIVACY AND SECURITY TABLES
-- =====================================================

-- Privacy settings table
CREATE TABLE IF NOT EXISTS user_privacy_settings (
    user_id BIGINT PRIMARY KEY,
    account_private BOOLEAN DEFAULT FALSE,
    approve_followers BOOLEAN DEFAULT FALSE,
    hide_following_list BOOLEAN DEFAULT FALSE,
    hide_followers_list BOOLEAN DEFAULT FALSE,
    activity_visibility VARCHAR(20) DEFAULT 'public',
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Block list table
CREATE TABLE IF NOT EXISTS user_blocks (
    id BIGSERIAL PRIMARY KEY,
    blocker_id BIGINT NOT NULL,
    blocked_id BIGINT NOT NULL,
    blocked_type VARCHAR(20) DEFAULT 'user',
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(blocker_id, blocked_id, blocked_type)
);

-- Create indexes for block queries
CREATE INDEX idx_user_blocks_blocker ON user_blocks(blocker_id);
CREATE INDEX idx_user_blocks_blocked ON user_blocks(blocked_id, blocked_type);

-- 3. NOTIFICATION TABLES
-- =====================================================

-- Notification preferences table
CREATE TABLE IF NOT EXISTS notification_preferences (
    user_id BIGINT PRIMARY KEY,
    new_follower BOOLEAN DEFAULT TRUE,
    follow_request BOOLEAN DEFAULT TRUE,
    milestone_reached BOOLEAN DEFAULT TRUE,
    weekly_digest BOOLEAN DEFAULT TRUE,
    recommendations BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Email notification queue
CREATE TABLE IF NOT EXISTS email_notification_queue (
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
    created_at TIMESTAMP DEFAULT NOW()
);

-- Create indexes for notification queries
CREATE INDEX idx_email_notification_queue_status_scheduled ON email_notification_queue(status, scheduled_for);
CREATE INDEX idx_email_notification_queue_recipient ON email_notification_queue(recipient_id, status);

-- 4. UPDATE EXISTING TABLES
-- =====================================================

-- Add social features to existing WordPress users table
ALTER TABLE wordpress_users ADD COLUMN IF NOT EXISTS follower_count INT DEFAULT 0;
ALTER TABLE wordpress_users ADD COLUMN IF NOT EXISTS following_count INT DEFAULT 0;
ALTER TABLE wordpress_users ADD COLUMN IF NOT EXISTS is_verified BOOLEAN DEFAULT FALSE;
ALTER TABLE wordpress_users ADD COLUMN IF NOT EXISTS social_bio TEXT;
ALTER TABLE wordpress_users ADD COLUMN IF NOT EXISTS social_metadata JSONB;

-- Link social activities to restaurants
ALTER TABLE restaurants ADD COLUMN IF NOT EXISTS follower_count INT DEFAULT 0;
ALTER TABLE restaurants ADD COLUMN IF NOT EXISTS last_activity_at TIMESTAMP;

-- Add indexes for social queries on existing tables
CREATE INDEX IF NOT EXISTS idx_wp_users_social ON wordpress_users(follower_count DESC, is_verified);
CREATE INDEX IF NOT EXISTS idx_restaurants_social ON restaurants(follower_count DESC);

-- 5. MATERIALIZED VIEWS FOR PERFORMANCE
-- =====================================================

-- Trending users view (refresh hourly)
CREATE MATERIALIZED VIEW IF NOT EXISTS trending_users AS
SELECT 
    u.wp_user_id,
    u.display_name,
    COUNT(DISTINCT f.follower_id) as new_followers_week,
    u.follower_count,
    CASE 
        WHEN u.follower_count > 0 
        THEN (COUNT(DISTINCT f.follower_id)::FLOAT / u.follower_count)
        ELSE 0 
    END as growth_rate
FROM wordpress_users u
JOIN user_follows f ON f.followed_id = u.wp_user_id 
    AND f.followed_type = 'user'
    AND f.created_at > NOW() - INTERVAL '7 days'
    AND f.status = 'active'
GROUP BY u.wp_user_id, u.display_name, u.follower_count
HAVING COUNT(DISTINCT f.follower_id) > 5
ORDER BY growth_rate DESC
LIMIT 100;

CREATE INDEX idx_trending_users ON trending_users(wp_user_id);

-- User taste overlap view (refresh daily)
CREATE MATERIALIZED VIEW IF NOT EXISTS user_taste_overlaps AS
SELECT 
    a.wp_user_id as user_a,
    b.wp_user_id as user_b,
    COUNT(DISTINCT a.vibe_id) as common_vibes,
    ARRAY_AGG(DISTINCT v.vibe_name ORDER BY v.vibe_name) as shared_vibes
FROM wp_user_vibe_interactions a
JOIN wp_user_vibe_interactions b ON a.vibe_id = b.vibe_id
JOIN vibe_definitions v ON v.id = a.vibe_id
WHERE a.wp_user_id < b.wp_user_id
    AND a.interaction_type IN ('favorite', 'search', 'view')
    AND b.interaction_type IN ('favorite', 'search', 'view')
    AND a.created_at > NOW() - INTERVAL '90 days'
    AND b.created_at > NOW() - INTERVAL '90 days'
GROUP BY a.wp_user_id, b.wp_user_id
HAVING COUNT(DISTINCT a.vibe_id) >= 3;

CREATE INDEX idx_taste_overlap_users ON user_taste_overlaps(user_a, user_b);

-- 6. TRIGGERS FOR DATA CONSISTENCY
-- =====================================================

-- Update timestamp trigger
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Apply update trigger to tables with updated_at
CREATE TRIGGER update_user_follows_updated_at BEFORE UPDATE ON user_follows
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_user_privacy_settings_updated_at BEFORE UPDATE ON user_privacy_settings
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_notification_preferences_updated_at BEFORE UPDATE ON notification_preferences
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- 7. HELPER FUNCTIONS
-- =====================================================

-- Function to update follow stats
CREATE OR REPLACE FUNCTION update_follow_stats(p_entity_id BIGINT, p_entity_type VARCHAR)
RETURNS VOID AS $$
BEGIN
    INSERT INTO follow_stats_cache (entity_id, entity_type, followers_count, following_count, last_calculated)
    VALUES (
        p_entity_id,
        p_entity_type,
        (SELECT COUNT(*) FROM user_follows WHERE followed_id = p_entity_id AND followed_type = p_entity_type AND status = 'active'),
        CASE 
            WHEN p_entity_type = 'user' THEN 
                (SELECT COUNT(*) FROM user_follows WHERE follower_id = p_entity_id AND status = 'active')
            ELSE 0
        END,
        NOW()
    )
    ON CONFLICT (entity_id, entity_type) DO UPDATE
    SET 
        followers_count = EXCLUDED.followers_count,
        following_count = EXCLUDED.following_count,
        last_calculated = NOW();
END;
$$ LANGUAGE plpgsql;

-- Function to check if user can view content
CREATE OR REPLACE FUNCTION can_user_view_content(p_viewer_id BIGINT, p_content_owner_id BIGINT)
RETURNS BOOLEAN AS $$
DECLARE
    v_blocked BOOLEAN;
    v_private BOOLEAN;
    v_following BOOLEAN;
BEGIN
    -- Check if blocked
    SELECT EXISTS(
        SELECT 1 FROM user_blocks 
        WHERE (blocker_id = p_viewer_id AND blocked_id = p_content_owner_id)
           OR (blocker_id = p_content_owner_id AND blocked_id = p_viewer_id)
    ) INTO v_blocked;
    
    IF v_blocked THEN
        RETURN FALSE;
    END IF;
    
    -- Check if account is private
    SELECT account_private INTO v_private
    FROM user_privacy_settings
    WHERE user_id = p_content_owner_id;
    
    IF v_private IS NULL OR NOT v_private THEN
        RETURN TRUE;
    END IF;
    
    -- If private, check if following
    SELECT EXISTS(
        SELECT 1 FROM user_follows
        WHERE follower_id = p_viewer_id 
          AND followed_id = p_content_owner_id
          AND followed_type = 'user'
          AND status = 'active'
    ) INTO v_following;
    
    RETURN v_following;
END;
$$ LANGUAGE plpgsql;

-- 8. SAMPLE DATA FOR TESTING (OPTIONAL)
-- =====================================================

-- Uncomment below to insert sample data for testing

/*
-- Sample privacy settings
INSERT INTO user_privacy_settings (user_id, account_private, activity_visibility)
VALUES 
    (1, false, 'public'),
    (2, true, 'followers'),
    (3, false, 'public')
ON CONFLICT DO NOTHING;

-- Sample notification preferences
INSERT INTO notification_preferences (user_id, new_follower, weekly_digest)
VALUES 
    (1, true, true),
    (2, true, false),
    (3, false, true)
ON CONFLICT DO NOTHING;
*/

-- =====================================================
-- END OF SOCIAL TABLES CREATION SCRIPT
-- =====================================================