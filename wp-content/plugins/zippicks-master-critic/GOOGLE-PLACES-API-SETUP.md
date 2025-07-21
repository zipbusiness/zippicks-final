# Google Places API Setup Guide for ZipPicks Master Critic

## Overview
The Master Critic plugin has built-in support for Google Places API to enhance business data with verified information including addresses, phone numbers, hours, ratings, and photos.

## Step 1: Enable Google Places API in Google Cloud Console

1. **Go to Google Cloud Console**
   - Visit: https://console.cloud.google.com/

2. **Create or Select a Project**
   - Click on the project dropdown (top left)
   - Create a new project or select existing one

3. **Enable APIs**
   - Go to "APIs & Services" → "Library"
   - Search for and enable these APIs:
     - **Places API** (required)
     - **Maps JavaScript API** (optional, for map display)
     - **Geocoding API** (optional, for address validation)

4. **Create API Credentials**
   - Go to "APIs & Services" → "Credentials"
   - Click "Create Credentials" → "API Key"
   - Copy the generated API key

5. **Secure Your API Key** (Important!)
   - Click on your API key to edit it
   - Under "Application restrictions":
     - Select "HTTP referrers (web sites)"
     - Add your website URLs:
       - `https://yourdomain.com/*`
       - `https://www.yourdomain.com/*`
       - For development: `http://localhost/*`
   - Under "API restrictions":
     - Select "Restrict key"
     - Select only the APIs you enabled (Places API, etc.)
   - Click "Save"

## Step 2: Configure API Key in ZipPicks

1. **Navigate to Settings**
   - Go to WordPress Admin → Master Critic → Settings

2. **Add Google Places API Key**
   - Find the "Google Places API Key" field under "Hybrid Data API Keys"
   - Paste your API key
   - Save settings

## Step 3: Set Up Billing (Required by Google)

Google requires billing to be enabled, but provides $200 free credit monthly:

1. **Enable Billing**
   - In Google Cloud Console, go to "Billing"
   - Add a payment method
   - You won't be charged unless you exceed $200/month

2. **Set Budget Alerts** (Recommended)
   - Go to "Budgets & alerts"
   - Create a budget for your project
   - Set alert at 50%, 90%, and 100% of your budget

## Step 4: Monitor Usage

### Google Cloud Console
- Go to "APIs & Services" → "Dashboard"
- View API usage charts and quotas

### ZipPicks Internal Tracking
The plugin automatically tracks API usage to prevent exceeding limits:
- Monthly budget: $200 (free tier)
- Cost per request: ~$0.017
- Free requests per month: ~11,700

## Step 5: Test Your Configuration

Run the included test script:

```bash
wp eval-file wp-content/plugins/zippicks-master-critic/test-google-places-api.php
```

This will verify:
- API key is properly configured
- API is accessible
- Place search works correctly
- Place details can be retrieved

## API Features Used by Master Critic

### 1. Place Search
- Find businesses by name and location
- Verify business existence
- Get Google Place IDs

### 2. Place Details
- Business hours
- Phone numbers
- Website URLs
- Current operational status
- User ratings and review counts
- Price levels
- Business categories
- Photos

### 3. Enhanced Data Integration
The Master Critic uses Google Places data to:
- Verify AI-generated business information
- Add missing contact details
- Confirm current operational status
- Enhance recommendations with real-time data

## Troubleshooting

### "REQUEST_DENIED" Error
- Check API key restrictions match your domain
- Ensure Places API is enabled in Google Cloud Console
- Verify billing is enabled on your Google Cloud account

### "OVER_QUERY_LIMIT" Error
- Check your Google Cloud Console for quota status
- Reduce request frequency
- Consider upgrading to paid tier if needed

### "INVALID_REQUEST" Error
- Verify the business name and location are correct
- Check for special characters in search queries

### API Key Not Working
1. Regenerate the key in Google Cloud Console
2. Remove all restrictions temporarily to test
3. Add restrictions back one by one

## Cost Optimization

The plugin implements several cost-saving measures:

1. **Intelligent Caching**
   - Results cached for 7 days
   - Prevents duplicate API calls

2. **Selective Enhancement**
   - Only queries Google for high-value data
   - AI handles most generation without API calls

3. **Budget Protection**
   - Automatic tracking of monthly usage
   - Stops queries when approaching limit

## Security Best Practices

1. **Never expose API key in frontend code**
2. **Always use HTTPS in production**
3. **Restrict API key to your domains**
4. **Monitor usage regularly**
5. **Set up billing alerts**

## Integration with Master Critic Workflow

When generating business lists:

1. **AI Generation First**
   - Claude/GPT generates initial list
   - No Google API calls yet

2. **Selective Enhancement** (Optional)
   - For businesses needing verification
   - Fetch current operational status
   - Add missing contact information

3. **Hybrid Confidence Score**
   - Higher confidence when Google data confirms AI
   - Flags discrepancies for review

This approach maximizes the free tier while providing accurate, verified data.