# Master Critic API Setup Summary

## Overview
The ZipPicks Master Critic plugin requires API keys to function. Here's everything you need to know about setting up the APIs.

## Required APIs

### 1. Anthropic API (REQUIRED)
The Master Critic uses Claude AI to generate business rankings.

**Setup Steps:**
1. Visit https://console.anthropic.com/account/keys
2. Create an API key
3. Add it in Master Critic > Settings > Anthropic API Key

**Model Selection:**
- **Claude 3 Opus**: Best quality, highest cost (~$15/1000 tokens)
- **Claude 3 Sonnet**: Balanced performance (~$3/1000 tokens) - RECOMMENDED
- **Claude 3 Haiku**: Fastest, lowest cost (~$0.25/1000 tokens)

**Note**: Some API keys may not have access to Opus. If you get a model error, switch to Sonnet.

### 2. Google Places API (RECOMMENDED)
Enhances AI-generated data with verified business information.

**Setup Steps:**
1. Go to https://console.cloud.google.com/
2. Create a new project (or select existing)
3. Enable "Places API" from the API Library
4. Create credentials (API Key)
5. Secure the key:
   - Add HTTP referrer restrictions
   - Limit to your domain(s)
   - Enable only Places API
6. Enable billing (required by Google, but includes $200/month free)
7. Add the key in Master Critic > Settings > Google Places API Key

**Features Provided:**
- Business verification
- Current operational status
- Phone numbers and websites
- Business hours
- User ratings and review counts
- Photos

### 3. OpenAI API (OPTIONAL)
Backup AI provider if Anthropic fails.

**Setup Steps:**
1. Visit https://platform.openai.com/api-keys
2. Create an API key
3. Add it in Master Critic > Settings > OpenAI API Key

**Models Available:**
- GPT-4o (Latest)
- GPT-4 Turbo (Recommended for JSON)
- GPT-3.5 Turbo (Faster, cheaper)

### 4. Yelp Fusion API (OPTIONAL)
Additional review data enhancement.

**Setup Steps:**
1. Visit https://www.yelp.com/developers/v3/manage_app
2. Create an app
3. Get your API key
4. Add it in Master Critic > Settings > Yelp API Key

## Quick Setup Options

### Option 1: Command Line Setup
```bash
# Interactive setup script
wp eval-file wp-content/plugins/zippicks-master-critic/setup-apis.php
```

### Option 2: WordPress Admin
1. Go to WordPress Admin
2. Navigate to Master Critic > Settings
3. Enter your API keys
4. Save settings

### Option 3: Manual Database Update
```bash
# Set Anthropic API key
wp option update zippicks_anthropic_api_key "your-key-here"

# Set Google Places API key  
wp option update zippicks_google_api_key "your-key-here"
```

## Testing Your APIs

### Test All APIs
```bash
# Test Anthropic/Claude
wp eval-file wp-content/plugins/zippicks-master-critic/test-anthropic-api.php

# Test Google Places
wp eval-file wp-content/plugins/zippicks-master-critic/test-google-places-api.php
```

### Test in WordPress Admin
1. Go to Master Critic > AI Generation
2. Fill in:
   - Business Category: Restaurant
   - Topic: Italian  
   - Location: Los Angeles
3. Click "Generate Prompt" then "Execute AI Generation"

## Cost Management

### Anthropic/Claude Costs
- Sonnet: ~$3 per 1000 tokens input, $15 per 1000 output
- Average list generation: ~4000 tokens = ~$0.06 per list
- Monthly estimate (100 lists): ~$6

### Google Places Costs
- $200/month free credit from Google
- $0.017 per Places API call
- Free calls per month: ~11,700
- Only used for verification, not every request

### Cost Optimization Built-in
- 7-day caching for repeated queries
- Selective API usage (only when needed)
- Budget tracking and limits
- Automatic fallback to AI-only mode

## Troubleshooting

### Anthropic API Issues

**Error: "model: claude-3-opus-20240229"**
- Your API key doesn't have Opus access
- Solution: Switch to Sonnet model in settings

**Error: "401 Unauthorized"**
- Invalid API key
- Solution: Check your key in Anthropic console

### Google Places API Issues

**Error: "REQUEST_DENIED"**
- API key restrictions don't match your domain
- Places API not enabled
- Billing not enabled
- Solution: Check all three in Google Cloud Console

**Error: "OVER_QUERY_LIMIT"**
- Exceeded free tier
- Solution: Check usage in Google Cloud Console

## Security Best Practices

1. **API Key Storage**
   - Keys are encrypted in database
   - Never commit keys to version control
   - Use environment variables in production

2. **Google API Restrictions**
   - Always restrict to your domains
   - Enable only required APIs
   - Set up billing alerts

3. **Rate Limiting**
   - Plugin implements automatic rate limiting
   - Configurable in settings
   - Prevents accidental overuse

## Environment Variables (Optional)

For production environments, you can use constants in wp-config.php:

```php
// Anthropic
define('ZIPPICKS_ANTHROPIC_API_KEY', 'your-key-here');

// Google Places
define('ZIPPICKS_GOOGLE_API_KEY', 'your-key-here');

// OpenAI
define('ZIPPICKS_OPENAI_API_KEY', 'your-key-here');

// Yelp
define('ZIPPICKS_YELP_API_KEY', 'your-key-here');
```

## Support

If you encounter issues:
1. Check the test scripts first
2. Enable debug mode: `define('ZIPPICKS_DEBUG', true);`
3. Check logs in `wp-content/debug.log`
4. Verify all steps in this guide

## Next Steps

After setting up APIs:
1. Test each API individually
2. Generate a test list
3. Monitor usage for the first week
4. Adjust rate limits if needed
5. Set up Google Cloud billing alerts