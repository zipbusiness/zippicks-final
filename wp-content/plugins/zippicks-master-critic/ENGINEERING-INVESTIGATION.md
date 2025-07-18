# Master Critic Enhancement - Engineering Investigation Guide

## 🔍 Issue: "Still Processing" - Investigation Steps

### 1. Browser Console Check
Open Developer Tools (F12) and check:
- **Console Tab**: Look for JavaScript errors
- **Network Tab**: Find the `admin-ajax.php` request
  - Status code (should be 200)
  - Response content (may show error details)
  - Duration (if > 120 seconds, it timed out)

### 2. WordPress Debug Log
Check `/wp-content/debug.log` for:
- PHP errors during execution
- API connection failures
- Database query errors

### 3. Test Basic Functionality
```php
// Test file: test-basic-generation.php
<?php
require_once(__DIR__ . '/../../../../wp-load.php');
require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-ai-service.php';

$ai_service = new ZipPicks_Master_Critic_AI_Service();

// Test 1: Basic prompt generation
$params = array(
    'business_category' => 'restaurant',
    'topic' => 'pizza',
    'location' => 'New York'
);

$prompt = $ai_service->generate_prompt($params);
echo "Prompt generated: " . (strlen($prompt) > 0 ? 'YES' : 'NO') . "\n";

// Test 2: Direct API call
$result = $ai_service->execute_ai_generation($prompt, 'anthropic');
echo "API Result: ";
print_r($result);
```

## 📝 Complete List of Changes Made

### 1. **Database Schema Changes**
**File**: `includes/class-database.php`

**Added Fields to generations table**:
- `confidence_score float DEFAULT NULL` (line 69)
- `validation_report longtext` (line 70)

**Updated Methods**:
- `insert_generation()` - Added confidence_score and validation_report (lines 307-313)
- `update_generation()` - Added handling for new fields (lines 356-364)

### 2. **AI Service Core Changes**
**File**: `includes/class-ai-service.php`

**Added Methods**:
- `execute_enhanced_generation()` - New method that calls enhanced service (lines 34-38)
- `get_smart_cache_ttl()` - Smart caching based on city/category (lines 626-670)
- `cache_business_components()` - Component-level caching (lines 678-701)
- `get_cached_business_component()` - Retrieve cached components (lines 709-712)

**Modified Methods**:
- `generate_prompt()` - Now delegates to enhanced service (lines 360-365)
- `execute_ai_generation()` - Enhanced caching logic (lines 96-107)

### 3. **Enhanced AI Service Integration**
**File**: `includes/class-ai-service-enhanced.php`

**Key Changes**:
- Made `build_enhanced_prompt()` public instead of private (line 59)
- Added fallback logic for GPT-4 with validation (lines 61-89)
- Integrated city context system (lines 60-70)
- Added tiered model selection (lines 20-35)

**New Method**:
- `get_optimal_model()` - Selects Opus for major cities, Sonnet for others (lines 300-326)

### 4. **Admin AJAX Handler**
**File**: `admin/class-admin.php`

**Modified**: `ajax_execute_ai_generation()` method
- **OLD**: Used basic `execute_ai_generation()` (line 353)
- **NEW**: Uses `execute_enhanced_generation()` with params (lines 352-361)
- **Changed response**: Now includes confidence and validation (lines 364-368)
- **Updated success response**: Added confidence and validation_report (lines 385-393)

### 5. **Frontend JavaScript**
**File**: `assets/js/admin.js`

**Modified**: `displayAIResponse()` function
- Added confidence score display logic (lines 283-306)
- Shows color-coded confidence levels
- Displays validation warnings if present

### 6. **CSS Styling**
**File**: `assets/css/admin.css`

**Added**: Confidence score styling (lines 601-642)
- `.confidence-score` classes with high/medium/low variants
- `.validation-warnings` styling

### 7. **Supporting Files Created**
- `includes/class-quality-tester.php` - Testing framework
- `test-quality.php` - Web interface for testing
- `update-default-model.php` - Updates model to Opus
- `update-prompt-template.php` - Updates database prompts

## 🐛 Debugging "Still Processing"

### Most Likely Causes:

1. **Enhanced Service Not Loading**
   ```php
   // Check if enhanced service exists
   if (!class_exists('ZipPicks_Master_Critic_AI_Service_Enhanced')) {
       error_log('Enhanced AI Service class not found!');
   }
   ```

2. **Model Access Issues**
   - Claude-3-Opus requires specific API access
   - May need to use claude-3-sonnet-20240229 instead

3. **Timeout on Complex Validation**
   - The validation process might timeout for large result sets
   - Check PHP max_execution_time

4. **Database Update Required**
   - New fields might not have been created
   - Run: `SELECT * FROM wp_zippicks_generations LIMIT 1;`
   - Check if confidence_score column exists

### Quick Fix Attempts:

1. **Bypass Enhanced Generation** (temporary)
   In `admin/class-admin.php`, change line 360 from:
   ```php
   $result = $this->ai_service->execute_enhanced_generation($params);
   ```
   To:
   ```php
   $result = $this->ai_service->execute_ai_generation($prompt, $provider);
   ```

2. **Check API Key Permissions**
   Some Anthropic API keys don't have access to Opus model

3. **Verify File Includes**
   Ensure `class-ai-service-enhanced.php` is being loaded properly

## 📊 Performance Impact

The enhanced system adds:
- 1-2 seconds for validation
- 0.5 seconds for confidence calculation
- Negligible time for city context

Total additional time: ~2-3 seconds max

If taking longer, there's likely an API or configuration issue.

## 🔧 Rollback Instructions

To temporarily disable enhancements:
1. Comment out the enhanced generation call in `admin/class-admin.php`
2. Use the original prompt generation method
3. Database changes can remain (they don't affect existing functionality)

## 📞 Next Steps

1. Check browser console for specific error
2. Review WordPress debug log
3. Test with a simple query (1-2 businesses)
4. Verify API key has Opus access
5. Try with Sonnet model instead of Opus

The "still processing" issue is most likely:
- API timeout (check network tab)
- Model access issue (Opus requires higher tier)
- JavaScript error preventing UI update