# ZipPicks Business Intelligence Plugin

Enterprise-grade business data integration with ZipBusiness.ai API for the ZipPicks platform.

## Features

- Fetch and cache restaurant/business data from ZipBusiness API
- REST API endpoints for accessing business data
- Advanced caching with Redis support
- Comprehensive logging and monitoring
- Independent configuration management

## Installation

1. Upload the plugin to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. Configure API settings (see Configuration section)

## Configuration

### Method 1: Admin Interface

1. Navigate to **Business Intelligence → Settings**
2. Enter your ZipBusiness API URL and API Key
3. Save settings

### Method 2: Import from Master Critic

If you have the Master Critic plugin configured:

1. Go to **Business Intelligence → Settings**
2. Click **"Import Settings from Master Critic"**
3. Settings will be copied and plugins will operate independently

### Method 3: Environment Variables

Set these in your server environment:

```bash
ZIPPICKS_BI_API_URL=https://zipbusiness-api.onrender.com
ZIPPICKS_BI_API_KEY=your-api-key-here
```

### Method 4: PHP Constants

Add to your `wp-config.php`:

```php
define('ZIPPICKS_BI_API_URL', 'https://zipbusiness-api.onrender.com');
define('ZIPPICKS_BI_API_KEY', 'your-api-key-here');
```

## Usage

### PHP API

```php
// Get the service
$business_service = zippicks()->get('business_intelligence.service');

// Get businesses for a city
$businesses = $business_service->get_city_businesses('Berkeley', 'CA');

// Get specific business
$business = $business_service->get_business_by_zpid('zpid-123');
```

### REST API

```bash
# Get businesses by city
GET /wp-json/zippicks/v1/businesses/city/Berkeley?state=CA

# Get specific business
GET /wp-json/zippicks/v1/businesses/zpid-123

# Health check
GET /wp-json/zippicks/v1/businesses/health
```

## Testing

Visit `/wp-content/plugins/zippicks-business-intelligence/test-api-connection.php` to:

- Check configuration status
- Test API connectivity
- View sample data
- Check cache statistics

## Requirements

- WordPress 5.8+
- PHP 7.4+
- ZipPicks Foundation plugin
- Valid ZipBusiness API credentials

## Support

For issues or questions, contact the ZipPicks engineering team.