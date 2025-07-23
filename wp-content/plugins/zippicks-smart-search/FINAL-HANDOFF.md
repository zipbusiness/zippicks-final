# ZipPicks Smart Search - Final Handoff for Production

## 🚨 Critical Blockers for Production

### 1. PostgreSQL Database Tables Required

The following tables must be created in the PostgreSQL database to support the Smart Search functionality:

#### **1.1 search_queries**
Stores all search queries for analytics and optimization.

```sql
CREATE TABLE search_queries (
    id SERIAL PRIMARY KEY,
    query_text VARCHAR(255) NOT NULL,
    normalized_query VARCHAR(255) NOT NULL, -- Lowercase, trimmed, stop words removed
    intent VARCHAR(50) NOT NULL CHECK (intent IN ('vibe', 'utility', 'hybrid', 'business', 'category')),
    user_id INTEGER,
    session_id VARCHAR(128),
    ip_address INET,
    location_lat NUMERIC(10, 7),
    location_lng NUMERIC(10, 7),
    location_city VARCHAR(100),
    location_state VARCHAR(50),
    result_count INTEGER DEFAULT 0,
    clicked_result_count INTEGER DEFAULT 0,
    response_time_ms INTEGER,
    cache_hit BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_query_text (query_text),
    INDEX idx_normalized_query (normalized_query),
    INDEX idx_intent (intent),
    INDEX idx_created_at (created_at),
    INDEX idx_location (location_lat, location_lng)
);
```

#### **1.2 search_clicks**
Tracks which results users click on from searches.

```sql
CREATE TABLE search_clicks (
    id SERIAL PRIMARY KEY,
    query_id INTEGER NOT NULL REFERENCES search_queries(id),
    zpid VARCHAR(50) NOT NULL,
    position INTEGER NOT NULL, -- Position in search results (1-based)
    click_time TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    dwell_time_seconds INTEGER, -- Time spent on clicked result
    
    -- Indexes
    INDEX idx_query_id (query_id),
    INDEX idx_zpid (zpid),
    INDEX idx_click_time (click_time)
);
```

#### **1.3 search_demand**
Tracks demand for businesses not yet in the system.

```sql
CREATE TABLE search_demand (
    id SERIAL PRIMARY KEY,
    zpid VARCHAR(50) NOT NULL,
    business_name VARCHAR(255) NOT NULL,
    location_lat NUMERIC(10, 7),
    location_lng NUMERIC(10, 7),
    location_address TEXT,
    demand_count INTEGER DEFAULT 1,
    last_requested TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    first_requested TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    
    -- Unique constraint to prevent duplicates
    UNIQUE(zpid),
    
    -- Indexes
    INDEX idx_demand_count (demand_count DESC),
    INDEX idx_last_requested (last_requested DESC)
);
```

#### **1.4 search_notifications**
Stores email notifications for "coming soon" businesses.

```sql
CREATE TABLE search_notifications (
    id SERIAL PRIMARY KEY,
    zpid VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    location_lat NUMERIC(10, 7),
    location_lng NUMERIC(10, 7),
    notification_sent BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    
    -- Prevent duplicate notifications
    UNIQUE(zpid, email),
    
    -- Indexes
    INDEX idx_zpid (zpid),
    INDEX idx_email (email),
    INDEX idx_notification_sent (notification_sent)
);
```

#### **1.5 search_autocomplete_cache**
Caches popular autocomplete suggestions for performance.

```sql
CREATE TABLE search_autocomplete_cache (
    id SERIAL PRIMARY KEY,
    prefix VARCHAR(50) NOT NULL,
    suggestion_type VARCHAR(20) CHECK (suggestion_type IN ('query', 'business', 'vibe', 'category')),
    suggestion_value VARCHAR(255) NOT NULL,
    zpid VARCHAR(50), -- Only for business suggestions
    usage_count INTEGER DEFAULT 1,
    last_used TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_prefix (prefix),
    INDEX idx_usage_count (usage_count DESC),
    UNIQUE(prefix, suggestion_type, suggestion_value)
);
```

#### **1.6 search_analytics_daily**
Aggregated daily analytics for reporting.

```sql
CREATE TABLE search_analytics_daily (
    date DATE PRIMARY KEY,
    total_searches INTEGER DEFAULT 0,
    unique_users INTEGER DEFAULT 0,
    avg_response_time_ms NUMERIC(10, 2),
    cache_hit_rate NUMERIC(5, 2), -- Percentage
    click_through_rate NUMERIC(5, 2), -- Percentage
    top_queries JSONB, -- Array of {query, count} objects
    top_intents JSONB, -- Object with intent counts
    top_clicked_businesses JSONB, -- Array of {zpid, name, clicks}
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
```

### 2. PostgreSQL API Endpoints Required

The following RESTful endpoints need to be created in the PostgreSQL API service:

#### **2.1 POST /api/v1/search/track**
Records search queries for analytics.

**Request:**
```json
{
    "query": "romantic dinner",
    "normalized_query": "romantic dinner",
    "intent": "vibe",
    "location": {
        "lat": 34.0522,
        "lng": -118.2437,
        "city": "Los Angeles",
        "state": "CA"
    },
    "result_count": 15,
    "response_time_ms": 145,
    "cache_hit": false,
    "user_id": 123,
    "session_id": "abc123"
}
```

**Response:**
```json
{
    "success": true,
    "query_id": 45678
}
```

**Implementation Notes:**
- Insert into `search_queries` table
- Return the generated query_id for click tracking
- Handle high volume (expect 1000+ requests/minute)

#### **2.2 POST /api/v1/search/demand**
Tracks demand for missing businesses.

**Request:**
```json
{
    "zpid": "starbucks-hollywood-bl-12345",
    "name": "Starbucks",
    "location": {
        "lat": 34.0522,
        "lng": -118.2437,
        "address": "123 Hollywood Blvd, Los Angeles, CA"
    },
    "user_email": "user@example.com" // Optional
}
```

**Response:**
```json
{
    "success": true,
    "demand_count": 5,
    "message": "Demand recorded"
}
```

**Implementation Notes:**
- Upsert into `search_demand` table
- Increment demand_count if exists
- Update last_requested timestamp

#### **2.3 POST /api/v1/search/click**
Tracks search result clicks.

**Request:**
```json
{
    "query_id": 45678,
    "zpid": "bestia-downtown-la",
    "position": 3,
    "query": "italian restaurant" // For fallback if query_id missing
}
```

**Response:**
```json
{
    "success": true,
    "click_id": 98765
}
```

**Implementation Notes:**
- Insert into `search_clicks` table
- Update clicked_result_count in `search_queries`
- Handle missing query_id gracefully

#### **2.4 GET /api/v1/search/analytics**
Retrieves search analytics data.

**Request Parameters:**
- `start_date`: ISO date (default: 7 days ago)
- `end_date`: ISO date (default: today)
- `limit`: Number of top items (default: 10)

**Response:**
```json
{
    "success": true,
    "data": {
        "summary": {
            "total_searches": 15234,
            "unique_users": 3421,
            "avg_response_time_ms": 187,
            "cache_hit_rate": 82.5,
            "click_through_rate": 34.2
        },
        "top_queries": [
            {"query": "coffee shops", "count": 234},
            {"query": "romantic dinner", "count": 189}
        ],
        "intent_distribution": {
            "vibe": 45.2,
            "utility": 32.1,
            "hybrid": 22.7
        },
        "searches_by_day": [
            {"date": "2024-01-15", "count": 2341},
            {"date": "2024-01-16", "count": 2156}
        ]
    }
}
```

**Implementation Notes:**
- Query from `search_analytics_daily` for performance
- Fall back to `search_queries` for real-time data
- Cache results for 5 minutes

#### **2.5 GET /api/v1/search/demand/analytics**
Gets demand tracking analytics.

**Request Parameters:**
- `limit`: Number of results (default: 20)
- `min_demand`: Minimum demand count (default: 2)

**Response:**
```json
{
    "success": true,
    "data": {
        "total_demanded": 342,
        "unique_businesses": 156,
        "top_demanded": [
            {
                "zpid": "in-n-out-venice-beach",
                "name": "In-N-Out Burger",
                "demand_count": 45,
                "location": "Venice Beach, CA"
            }
        ],
        "by_location": {
            "Los Angeles": 123,
            "New York": 89
        }
    }
}
```

**Implementation Notes:**
- Query `search_demand` table
- Group by location for geographic insights
- Order by demand_count DESC

#### **2.6 POST /api/v1/search/notify**
Registers for coming soon notifications.

**Request:**
```json
{
    "zpid": "nobu-malibu",
    "email": "user@example.com",
    "location": {
        "lat": 34.0522,
        "lng": -118.2437
    }
}
```

**Response:**
```json
{
    "success": true,
    "message": "Notification registered",
    "notification_id": 7890
}
```

**Implementation Notes:**
- Insert into `search_notifications` table
- Handle duplicate email/zpid pairs gracefully
- Trigger email queue if business becomes available

#### **2.7 GET /api/v1/search/autocomplete** (Optional Enhancement)
Real-time autocomplete suggestions.

**Request Parameters:**
- `prefix`: Search prefix (min 2 chars)
- `lat`: User latitude
- `lng`: User longitude
- `limit`: Max suggestions (default: 10)

**Response:**
```json
{
    "success": true,
    "suggestions": [
        {
            "type": "business",
            "value": "Bestia",
            "label": "Bestia - Italian Restaurant",
            "zpid": "bestia-downtown-la",
            "distance": 2.3
        },
        {
            "type": "vibe",
            "value": "romantic dinner",
            "label": "romantic dinner"
        },
        {
            "type": "category",
            "value": "Italian",
            "label": "Italian Restaurants"
        }
    ]
}
```

**Implementation Notes:**
- Query `search_autocomplete_cache` first
- Fall back to business/vibe/category tables
- Update cache with popular suggestions
- Consider location proximity

### 3. Database Performance Considerations

#### **3.1 Required Indexes**
All indexes are included in the table definitions above, but ensure they're created for:
- Query performance on high-traffic columns
- Geographic queries (lat/lng)
- Time-based queries (created_at, date)
- Lookup fields (zpid, email)

#### **3.2 Partitioning Strategy**
For large-scale deployment, consider partitioning:
- `search_queries`: Partition by created_at (monthly)
- `search_clicks`: Partition by click_time (monthly)
- `search_analytics_daily`: Partition by date (yearly)

#### **3.3 Maintenance Jobs**
Create scheduled jobs for:
- Daily aggregation into `search_analytics_daily`
- Cleanup of old `search_queries` (keep 90 days)
- Update `search_autocomplete_cache` with trending queries
- Process `search_notifications` queue

### 4. API Security Requirements

#### **4.1 Authentication**
- API key validation on all endpoints
- Rate limiting per API key
- IP allowlist for production WordPress servers

#### **4.2 Input Validation**
- Validate ZPID format: `/^[a-zA-Z0-9\-]+$/`
- Validate coordinates: lat (-90 to 90), lng (-180 to 180)
- Sanitize all string inputs
- Limit query length to 255 characters

#### **4.3 Response Security**
- Never expose internal IDs or sensitive data
- Use HTTPS only
- Add CORS headers for WordPress domain only

### 5. Integration Updates Required

Once the PostgreSQL endpoints are live, update these files:

#### **5.1 Update API_Client class**
File: `includes/class-api-client.php`

Replace mock endpoints with real URLs:
```php
private $endpoints = [
    'track_search' => '/api/v1/search/track',
    'track_demand' => '/api/v1/search/demand',
    'track_click' => '/api/v1/search/click',
    'get_analytics' => '/api/v1/search/analytics',
    'get_demand_analytics' => '/api/v1/search/demand/analytics',
    'register_notification' => '/api/v1/search/notify',
    'autocomplete' => '/api/v1/search/autocomplete'
];
```

#### **5.2 Remove Mock Data**
File: `includes/class-api-client.php`

Remove the mock autocomplete data in `get_autocomplete()` method and implement real API call.

#### **5.3 Update Analytics Class**
File: `includes/class-analytics.php`

Update to use real analytics endpoint instead of WordPress options.

### 6. Testing Requirements

#### **6.1 API Endpoint Tests**
- [ ] Test each endpoint with valid data
- [ ] Test error handling (invalid data, missing fields)
- [ ] Test rate limiting
- [ ] Test high concurrent load
- [ ] Verify response times < 200ms

#### **6.2 Integration Tests**
- [ ] Search flow end-to-end with tracking
- [ ] Autocomplete with real data
- [ ] Click tracking accuracy
- [ ] Demand tracking increments
- [ ] Analytics data accuracy

#### **6.3 Performance Tests**
- [ ] 1000 searches/minute load test
- [ ] Database query performance under load
- [ ] Cache effectiveness (>80% hit rate)
- [ ] API response times at scale

### 7. Monitoring Setup

#### **7.1 Database Monitoring**
- Query performance metrics
- Table sizes and growth rates
- Index usage statistics
- Slow query log analysis

#### **7.2 API Monitoring**
- Endpoint response times
- Error rates by endpoint
- Request volume trends
- Rate limit violations

#### **7.3 Business Metrics**
- Search volume trends
- Popular queries
- Click-through rates
- Demand tracking insights

### 8. Timeline Estimate

**Backend Team Tasks:**
1. Create PostgreSQL tables: 4-6 hours
2. Implement API endpoints: 16-24 hours
3. Testing and optimization: 8-12 hours
4. Documentation: 2-4 hours

**Total Backend Estimate**: 30-46 hours (4-6 days)

**Frontend Integration Tasks:**
1. Update API client: 2-4 hours
2. Remove mock data: 1 hour
3. Integration testing: 4-6 hours
4. Production deployment: 2-4 hours

**Total Frontend Estimate**: 9-15 hours (1-2 days)

### 9. Launch Readiness Checklist

**Backend Complete:**
- [ ] All PostgreSQL tables created
- [ ] All API endpoints implemented
- [ ] API documentation provided
- [ ] Load testing passed
- [ ] Monitoring configured

**Frontend Ready:**
- [ ] API client updated
- [ ] Mock data removed
- [ ] Integration tests passed
- [ ] Error tracking verified
- [ ] Performance benchmarks met

**Go-Live Requirements:**
- [ ] API keys provisioned
- [ ] CORS configured
- [ ] SSL certificates valid
- [ ] Backup strategy in place
- [ ] Rollback plan documented

---

## Contact for Questions

**Frontend Plugin**: Complete and ready for integration
**Backend Requirements**: This document contains all specifications
**Timeline**: Can launch within 1 week of backend completion

The Smart Search plugin is architecturally complete and waiting only for the PostgreSQL backend implementation detailed above.