# ZipBusiness API Integration - Master Critic Plugin Engineering Handoff

## Overview
Transform the Master Critic plugin from pure AI generation to a verified-only system using real restaurant data from the ZipBusiness API. The AI will only rank, score, and describe businesses that actually exist.

## System Architecture Change

### Current Flow
```
User Input → AI Generation → Create Fictional Businesses → Display List
```

### New Flow
```
User Input → Fetch Real Businesses from API → AI Ranks/Scores Real Data → Display Verified List
```

## Implementation Requirements

### 1. New Service Classes

#### A. `class-zipbusiness-api-client.php`
Location: `/includes/services/`

```php
class ZipPicks_Master_Critic_ZipBusiness_API_Client {
    
    const API_BASE_URL = 'https://zipbusiness-api.onrender.com/api/v1';
    const CACHE_GROUP = 'zipbusiness_api';
    const CITY_CACHE_TTL = 86400; // 24 hours
    
    /**
     * Fetch all restaurants for a city
     * @param string $city City name
     * @param string $state State code
     * @return array Restaurant data with zpids
     */
    public function get_city_restaurants($city, $state) {
        // 1. Check cache first
        // 2. Call API with verified_only=true
        // 3. Cache full response for 24 hours
        // 4. Return array of restaurants with zpids
    }
    
    /**
     * Enrich specific restaurants with additional data
     * @param array $zpids Array of ZipBusiness IDs
     * @return array Enriched restaurant data
     */
    public function enrich_restaurants($zpids) {
        // 1. Batch enrich up to 10 restaurants
        // 2. Cache enriched data for 7 days
        // 3. Include vibes, hours, photos
    }
    
    /**
     * Get API key from settings
     */
    private function get_api_key() {
        // Retrieve from encrypted settings
    }
}
```

#### B. `class-restaurant-validator.php`
Location: `/includes/services/`

```php
class ZipPicks_Master_Critic_Restaurant_Validator {
    
    /**
     * Validate AI response against real restaurant data
     * @param array $ai_selections AI's restaurant picks
     * @param array $api_restaurants Real restaurants from API
     * @return array Validated and matched restaurants
     */
    public function validate_selections($ai_selections, $api_restaurants) {
        // 1. Match AI selections to real zpids
        // 2. Replace any hallucinated data with API data
        // 3. Flag any unmatched selections
        // 4. Return only verified matches
    }
    
    /**
     * Match restaurant name to ZPID
     */
    private function match_to_zpid($ai_name, $api_restaurants) {
        // Fuzzy matching logic
        // Handle name variations
        // Return best match or null
    }
}
```

#### C. `class-vibe-integration-service.php`
Location: `/includes/services/`

```php
class ZipPicks_Master_Critic_Vibe_Integration {
    
    /**
     * Filter restaurants by relevant vibes
     * @param array $restaurants All restaurants
     * @param string $list_type Type of list (date_night, family_friendly, etc)
     * @return array Vibe-filtered restaurants
     */
    public function filter_by_vibes($restaurants, $list_type) {
        // 1. Map list type to relevant vibes
        // 2. Score restaurants by vibe confidence
        // 3. Return top candidates for AI
    }
    
    /**
     * Get vibe display priority for a restaurant
     */
    public function prioritize_vibes($restaurant, $context) {
        // Sort vibes by relevance to context
        // Return top 3-5 for display
    }
}
```

### 2. Modified AI Service

#### Update `class-ai-service.php`

Add new method for verified generation:

```php
/**
 * Execute verified restaurant ranking
 * @param array $params Generation parameters
 * @param array $verified_restaurants Real restaurants to rank
 * @return array AI rankings with verified data
 */
public function execute_verified_generation($params, $verified_restaurants) {
    // 1. Build prompt with real restaurant names
    // 2. Include up to 40 restaurants in prompt
    // 3. Ask AI to rank top 10 and score them
    // 4. Validate response against input list
    // 5. Return only verified matches
}
```

### 3. Database Schema Updates

```sql
-- Add to generations table
ALTER TABLE wp_zippicks_generations 
ADD COLUMN api_verification_status ENUM('verified', 'unverified', 'partial') DEFAULT 'unverified',
ADD COLUMN verified_count INT DEFAULT 0,
ADD COLUMN api_fetch_time TIMESTAMP NULL,
ADD COLUMN city_restaurant_count INT DEFAULT 0;

-- New table for API restaurant cache
CREATE TABLE wp_zippicks_api_restaurant_cache (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    zpid VARCHAR(20) NOT NULL UNIQUE,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(2) NOT NULL,
    restaurant_data LONGTEXT NOT NULL,
    enriched_data LONGTEXT NULL,
    cache_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    enriched_time TIMESTAMP NULL,
    INDEX idx_city_state (city, state),
    INDEX idx_cache_time (cache_time)
);

-- Link businesses to API data
ALTER TABLE wp_posts 
ADD COLUMN zpid VARCHAR(20) NULL,
ADD COLUMN api_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN api_confidence_score FLOAT NULL;
```

### 4. Modified Generation Workflow

#### Update `class-generation-page.php`

```php
// In ajax_execute_ai_generation() method:

// Step 1: Fetch real restaurants from API
$api_client = new ZipPicks_Master_Critic_ZipBusiness_API_Client();
$city_restaurants = $api_client->get_city_restaurants($location, $state);

if (empty($city_restaurants)) {
    // Fallback mode - mark as unverified
    return $this->execute_unverified_generation($params);
}

// Step 2: Filter by vibes if applicable
$vibe_service = new ZipPicks_Master_Critic_Vibe_Integration();
$filtered_restaurants = $vibe_service->filter_by_vibes(
    $city_restaurants, 
    $params['list_category']
);

// Step 3: Send to AI for ranking
$ai_response = $this->ai_service->execute_verified_generation(
    $params, 
    $filtered_restaurants
);

// Step 4: Validate AI response
$validator = new ZipPicks_Master_Critic_Restaurant_Validator();
$verified_selections = $validator->validate_selections(
    $ai_response['restaurants'],
    $city_restaurants
);

// Step 5: Enrich top 10
$top_10_zpids = array_column($verified_selections, 'zpid');
$enriched_data = $api_client->enrich_restaurants($top_10_zpids);

// Step 6: Create businesses with verified data
$this->create_verified_businesses($verified_selections, $enriched_data);
```

### 5. Prompt Template Updates

Create new template: `/includes/prompts/verified-ranking-prompt.txt`

```
VERIFIED RESTAURANT RANKING TASK
================================

You are ranking REAL restaurants that exist in {location}. 
Here are {count} verified restaurants to evaluate:

{restaurant_list}
[Each entry includes: Name, Cuisine, Price, Key Vibes, Address]

YOUR TASK:
1. Select the 10 BEST restaurants for: {topic}
2. Rank them 1-10
3. Score each across our 6 pillars (0-10 scale)
4. Write a 2-3 sentence editorial summary

IMPORTANT: 
- You may ONLY select from the provided list
- Do NOT invent any restaurants not on this list
- Base scores on the attributes provided
- Focus editorial voice on what makes each special

OUTPUT FORMAT:
{
  "restaurants": [
    {
      "rank": 1,
      "name": "EXACT name from list",
      "pillar_scores": {
        "food_quality": 9.5,
        "service": 9.2,
        "atmosphere": 9.0,
        "value": 8.5,
        "consistency": 9.3,
        "cultural_relevance": 9.7
      },
      "summary": "Editorial description focusing on strengths",
      "recommended_vibes": ["vibe1", "vibe2"] // from provided list
    }
  ]
}
```

### 6. Frontend Display Updates

#### Add Verification Badge
In list display templates, add:

```php
<?php if ($business->api_verified): ?>
    <span class="verified-badge">
        <svg>...</svg>
        Verified by ZipBusiness
    </span>
<?php endif; ?>
```

#### Hide External Ratings
Remove all instances of:
- Yelp ratings/counts
- Google ratings
- Review counts from other platforms

### 7. Admin UI Updates

#### Settings Page Additions
Add to settings:
- ZipBusiness API Key field (encrypted)
- API status indicator
- Cache management tools
- Verification statistics

#### Generation Page Updates
- Show "Fetching verified restaurants..." status
- Display count of available restaurants
- Show verification status for each generation
- Add "Unverified Mode" toggle for fallback

### 8. Error Handling

```php
class ZipPicks_Master_Critic_API_Error_Handler {
    
    public function handle_api_failure($error, $context) {
        // Log error with context
        // Send admin notification
        // Return graceful fallback
        // Mark generation as unverified
    }
    
    public function handle_partial_match($matched, $total) {
        // Log partial match scenario
        // Continue with available data
        // Flag for manual review
    }
}
```

### 9. Caching Strategy

```php
// Multi-layer cache configuration
const CACHE_LAYERS = [
    'city_data' => 86400,      // 24 hours
    'enriched' => 604800,      // 7 days  
    'vibe_filtered' => 3600,   // 1 hour
    'ai_rankings' => 604800    // 7 days
];

// Cache warming for major cities
wp_schedule_event(time(), 'daily', 'zippicks_warm_city_cache');
```

### 10. Testing Requirements

1. **Unit Tests**
   - API client methods
   - Restaurant validation logic
   - Vibe filtering algorithms
   - Cache operations

2. **Integration Tests**
   - Full generation workflow
   - API failure scenarios
   - Partial match handling
   - Cache invalidation

3. **Manual Testing**
   - Generate lists for 5 major cities
   - Verify all restaurants are real
   - Check enrichment data display
   - Test fallback mode

### 11. Migration Steps

1. Deploy new service classes
2. Run database migrations
3. Configure API credentials
4. Update prompt templates
5. Test with single city
6. Enable for all users
7. Monitor API usage

### 12. Performance Considerations

- Implement request queuing for API calls
- Use WordPress cron for cache warming
- Batch enrichment requests
- Monitor API response times
- Set up CloudFlare caching for API responses

### 13. Success Metrics

- Zero hallucinated restaurants
- API verification rate > 95%
- Cache hit rate > 80%
- Generation time < 10 seconds
- User trust score improvement

## Rollback Plan

If issues arise:
1. Feature flag to disable API integration
2. Revert to pure AI generation
3. Mark all lists as "unverified"
4. Investigate and fix issues
5. Re-enable with fixes

## Questions for Product Team

1. Should we show restaurant count before generation?
2. How to handle cities with < 10 restaurants?
3. Display "Last verified" timestamp?
4. Allow manual ZPID mapping for edge cases?
5. Premium feature: Real-time enrichment?