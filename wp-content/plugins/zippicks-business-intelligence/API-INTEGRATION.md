# ZipBusiness API Integration

## Overview

The ZipPicks Business Intelligence plugin connects to the ZipBusiness API independently. While it can share the same API credentials as the Master Critic plugin, each plugin maintains its own configuration for maximum flexibility and reliability.

## Configuration

### Independent API Settings

The Business Intelligence plugin has its own API configuration stored in WordPress options:

1. **API URL**: Stored in `zippicks_bi_api_url` option
2. **API Key**: Stored in `zippicks_bi_api_key` option (plaintext for now)

### Configuration Methods

You can configure the API settings in multiple ways:

1. **Admin Settings Page**: Navigate to Business Intelligence → Settings
2. **Import from Master Critic**: One-time copy of settings from Master Critic plugin
3. **Environment Variables**: Set in your server environment
4. **PHP Constants**: Define in wp-config.php

### Import from Master Critic

If you already have the Master Critic plugin configured, you can import its settings:

1. Go to Business Intelligence → Settings
2. Click "Import Settings from Master Critic"
3. The API URL and Key will be copied to Business Intelligence settings
4. After import, both plugins operate independently

## API Endpoints

The plugin connects to the following ZipBusiness API endpoints:

- `GET /api/v1/restaurants` - Get restaurants by city/state
- `GET /api/v1/restaurants/{zpid}` - Get specific restaurant by ZPID
- `GET /health` - API health check

## Testing

To test the API connection:

1. Navigate to `/wp-content/plugins/zippicks-business-intelligence/test-api-connection.php`
2. The test page will show:
   - Configuration status
   - API health check
   - Sample data fetch
   - Cache statistics
   - Database table status

## Usage Examples

### Get Businesses for a City

```php
// Using the service
$business_service = zippicks()->get('business_intelligence.service');
$businesses = $business_service->get_city_businesses('Berkeley', 'CA');

// Using REST API
GET /wp-json/zippicks/v1/businesses/city/Berkeley?state=CA
```

### Get Business by ZPID

```php
// Using the service
$business = $business_service->get_business_by_zpid('zpid-123');

// Using REST API
GET /wp-json/zippicks/v1/businesses/zpid-123
```

## Key Changes Made

1. **ConfigService** (`src/Services/ConfigService.php`):
   - Added `load_shared_api_settings()` method
   - Loads API settings from Master Critic plugin

2. **ZipBusinessAPIClient** (`src/Clients/ZipBusinessAPIClient.php`):
   - Updated endpoints to match Master Critic implementation
   - Uses `/api/v1/restaurants` instead of `/businesses`
   - Handles multiple response formats from the API

3. **BusinessService** (`src/Services/BusinessService.php`):
   - Added state parameter support (defaults to 'CA')
   - Updated all methods to pass state parameter

4. **REST API** (`includes/class-business-intelligence.php`):
   - Added state parameter to city endpoint
   - Updated callbacks to handle state parameter

## Configuration Priority

Settings are loaded in the following priority order (highest to lowest):

1. PHP Constants (defined in wp-config.php)
2. Environment Variables
3. WordPress Options (set via admin interface)
4. Default values

## Security Notes

- API keys should be kept secure and not committed to version control
- Consider using environment variables or PHP constants for production environments
- The import feature only copies settings once - changes in Master Critic won't affect Business Intelligence after import
- All API requests include proper error handling and retry logic

## Independence Benefits

- Business Intelligence can function without Master Critic plugin
- Different API endpoints or keys can be used if needed
- No risk of one plugin's deactivation affecting the other
- Easier testing and debugging with isolated configurations