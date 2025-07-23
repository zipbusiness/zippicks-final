# ZipPicks Geo Service Plugin

## Overview
WordPress plugin that integrates with the ZipBusiness API to provide location-based services for restaurant searches.

## Installation

1. Upload the plugin to `/wp-content/plugins/zippicks-geo-service/`
2. Activate the plugin through the WordPress admin panel
3. Configure the API connection (see below)

## Configuration

Add the following to your `wp-config.php` file:

```php
// ZipPicks API Configuration
define('ZIPPICKS_API_URL', 'https://zipbusiness-api.onrender.com');
define('ZIPPICKS_API_KEY', 'your-api-key-here');
```

## Features

### Distance Calculator
- Calculates distances between coordinates using the ZipBusiness API
- Access via: Geo Service → Tools → Distance Calculator

### Location Detection
- Detects user location from IP address
- Stores location preferences

### Nearby Restaurant Search
- Finds restaurants within a specified radius
- Uses the ZipBusiness API restaurant database

## API Endpoints

The plugin provides WordPress REST endpoints that proxy to the ZipBusiness API:

- `POST /wp-json/zippicks/v1/geo/distance` - Calculate distances
- `POST /wp-json/zippicks/v1/geo/detect` - Detect location from IP
- `POST /wp-json/zippicks/v1/geo/nearby` - Find nearby restaurants
- `POST /wp-json/zippicks/v1/geo/geocode` - Geocode addresses

## Troubleshooting

### API Key Not Set
- Check Settings page for configuration status
- Ensure `ZIPPICKS_API_KEY` is defined in wp-config.php

### Distance Calculator Not Working
- Verify API configuration is correct
- Check browser console for JavaScript errors
- Ensure the ZipBusiness API is accessible

## Files Updated for Production Use

1. **admin/class-admin.php** - Added API configuration display
2. **assets/js/admin.js** - Fixed distance calculator to use actual API
3. **includes/config.php** - Added centralized API configuration
4. **includes/class-rest-controller.php** - Fixed to proxy to external API
5. **zippicks-geo-service.php** - Fixed namespace issues

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Active ZipBusiness API key
- SSL certificate (for production)