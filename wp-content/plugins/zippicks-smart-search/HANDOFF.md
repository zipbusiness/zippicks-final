# ZipPicks Smart Search Plugin - Engineering Handoff

## Current Status

### ✅ Completed Work

1. **Plugin Foundation**
   - Main plugin file with proper initialization
   - Installer class (WITHOUT database tables - all data lives in PostgreSQL)
   - Business CPT registration with indexed `_zpid` meta field
   - Plugin activation/deactivation hooks

2. **Intent Classification System**
   - `Intent_Classifier` class that determines search intent (vibe, utility, hybrid)
   - Vibe pattern detection and expansion
   - Confidence scoring for intent classification
   - Query normalization and modifier extraction

3. **API Client**
   - Connects to PostgreSQL API at https://zipbusiness-api.onrender.com
   - Uses existing `/api/v1/restaurants` endpoint for search
   - Prepared methods for new endpoints that need to be created
   - Proper error handling and authentication via API key

4. **Search Engine Core**
   - Main search orchestration logic
   - Intent-based search routing
   - Result processing with Business CPT mapping
   - "Coming Soon" handling for missing businesses

5. **Tracking Classes** (API-based, no local DB)
   - `Demand_Tracker` - sends demand data to API
   - `Analytics` - sends search analytics to API

## ❌ Critical Architecture Fixes Applied

- **REMOVED all WordPress database table creation**
- **REMOVED all local database operations**
- **All data operations now go through PostgreSQL API**
- **WordPress only handles frontend display and caching**

## 🚨 Required API Endpoints (Need to be Created)

The following endpoints need to be added to the PostgreSQL API:

1. **Search Tracking**
   - `POST /api/v1/search/track` - Track search queries
   - `POST /api/v1/search/demand` - Track demand for missing businesses
   - `POST /api/v1/search/click` - Track result clicks
   - `GET /api/v1/search/analytics` - Get search analytics
   - `GET /api/v1/search/demand/analytics` - Get demand analytics

2. **Enhanced Search** (Optional but recommended)
   - `POST /api/v1/search` - Dedicated search endpoint with intent classification
   - Should accept query, location, and return classified results

## 📋 Remaining Work

### 1. REST Controller (`includes/class-rest-controller.php`)
Create WordPress REST endpoints that proxy to PostgreSQL API:
- `GET /wp-json/zippicks/v1/search` - Main search endpoint
- `POST /wp-json/zippicks/v1/search/notify/{zpid}` - Coming soon notifications

### 2. AJAX Handlers (`includes/class-ajax-handlers.php`)
- Handle search form submissions
- Autocomplete suggestions
- Click tracking

### 3. Cache Manager (`includes/class-cache-manager.php`)
- Use Pressidium Redis for caching search results
- Cache key generation with location/query hash
- TTL management per search type

### 4. Frontend Components
- **Search Bar Template** (`templates/search-bar.php`)
- **Result Templates** (`templates/result-vibe.php`, `templates/result-utility.php`)
- **JavaScript** (`assets/js/search.js`, `assets/js/autocomplete.js`)
- **CSS** (`assets/css/search.css`)

### 5. Admin Interface (`admin/class-admin.php`)
- Search analytics dashboard
- Demand tracking reports
- API connection status
- Settings page

### 6. Shortcodes
- `[zippicks_search]` - Display search bar
- `[zippicks_search_results]` - Display results container

## 🏗️ Implementation Guidelines

### Database Architecture
- **NO WordPress database tables** - Everything lives in PostgreSQL
- **Business CPT** only stores WordPress display data
- **All search data** goes through API to PostgreSQL

### Caching Strategy
1. Use Pressidium Redis (NOT local transients)
2. Cache search results for 5 minutes
3. Invalidate on new Business CPT creation
4. Different TTLs for vibe vs utility searches

### Performance Requirements
- <200ms search response time
- Implement request debouncing for autocomplete
- Lazy load result images
- Use WordPress REST API with browser caching

### Security
- Validate all input on both WordPress and API sides
- Rate limit searches per session
- Sanitize location data
- Use nonces for all AJAX requests

## 🔑 Configuration

### Required Constants (wp-config.php)
```php
define('ZIPPICKS_API_URL', 'https://zipbusiness-api.onrender.com');
define('ZIPPICKS_API_KEY', 'your-api-key-here');
```

### Plugin Options
- `zippicks_search_cache_ttl` - Cache TTL (default: 300)
- `zippicks_search_max_results` - Max results (default: 20)
- `zippicks_search_default_radius` - Default radius in miles (default: 10)

## 🧪 Testing Checklist

- [ ] Search returns results from PostgreSQL API
- [ ] Business CPT pages link correctly
- [ ] "Coming Soon" displays for missing businesses
- [ ] Demand tracking sends to API
- [ ] Intent classification works correctly
- [ ] Location detection integrates with Geo Service
- [ ] Caching improves performance
- [ ] No WordPress database tables created

## ⚠️ Critical Notes

1. **NEVER create WordPress database tables** - All data must flow through PostgreSQL
2. **Always check if Business CPT exists** before linking
3. **Track all "Coming Soon" clicks** for content prioritization
4. **Cache aggressively** but invalidate smartly
5. **Intent classification** determines search behavior

## 📞 Dependencies

- Geo Service plugin (for location detection)
- PostgreSQL API with new search endpoints
- Pressidium Redis for caching
- Business CPT (created by this plugin temporarily)

## 🚀 Next Steps

1. Create the missing API endpoints in PostgreSQL
2. Build the REST controller and AJAX handlers
3. Implement Redis caching
4. Create frontend templates and JavaScript
5. Test end-to-end search flow