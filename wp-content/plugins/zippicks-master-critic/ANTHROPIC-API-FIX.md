# Anthropic API Integration Fix

## What Was Fixed

1. **Added Claude Model Selection** - Added a dropdown in the Master Critic Settings page to select which Claude model to use (Opus, Sonnet, Haiku, etc.)

2. **Dynamic Model Usage** - Updated the AI service to use the selected model from settings instead of a hardcoded model

3. **Enhanced Error Handling** - Improved error messages with:
   - More detailed error information
   - HTTP status codes
   - Helpful suggestions for common errors (e.g., model not available with API key)
   - Better error display in the UI

4. **Debug Support** - Added comprehensive logging when ZIPPICKS_DEBUG is enabled

## How to Test

### 1. Update Settings
1. Go to **Master Critic > Settings**
2. Enter your Anthropic API key
3. Select a Claude model (start with Claude 3 Sonnet as it's the most balanced)
4. Save settings

### 2. Test API Connection
Run the included test script to verify your API connection:

```bash
wp eval-file wp-content/plugins/zippicks-master-critic/test-anthropic-api.php
```

This will:
- Verify your API key is configured
- Test the selected model
- Try alternative models if the default fails
- Show detailed error messages if something goes wrong

### 3. Test in UI
1. Go to **Master Critic > AI Generation**
2. Fill in the form:
   - Business Category: Restaurant
   - Topic: Italian
   - Location: Los Angeles
   - AI Provider: Claude (Anthropic)
3. Click "Generate Prompt"
4. Click "Execute AI Generation"

## Common Issues and Solutions

### Issue: "model: claude-3-sonnet-20240229" error
**Solution**: This usually means:
- Your API key doesn't have access to the selected model
- Try selecting Claude 3 Haiku (fastest, lowest cost) in settings
- Or try Claude 2.1 if you have an older API key

### Issue: 401 Unauthorized
**Solution**: Check your API key in settings - it may be incorrect or expired

### Issue: 400 Bad Request with model error
**Solution**: The selected model isn't available with your API key. Try a different model.

## Debug Mode

To enable detailed logging:

1. Add to wp-config.php:
```php
define('ZIPPICKS_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

2. Check logs at: `wp-content/debug.log`

## What Changed

### Files Modified:
1. `admin/class-settings-page.php` - Added Claude model dropdown and save logic
2. `includes/class-ai-service.php` - Use selected model, enhanced error handling
3. `admin/class-admin.php` - Pass through error details to frontend
4. `assets/js/admin.js` - Already handles error display properly

### Files Added:
1. `test-anthropic-api.php` - API testing script
2. `ANTHROPIC-API-FIX.md` - This documentation