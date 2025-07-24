# ZipPicks Favorites Plugin

A location-smart favorites system for the ZipPicks platform that allows users to save and manage their favorite restaurants with intelligent geospatial filtering.

## Features

### Core Functionality
- **Save/Unsave Favorites**: Heart icon toggle with animated feedback
- **Location-Based Filtering**: Find favorites by city, neighborhood, or proximity
- **Smart Search**: Search within favorites by name, cuisine, or location
- **Export Capabilities**: Export favorites as JSON or CSV for sharing

### Location Intelligence
- **Geolocation Support**: "Near Me" functionality using browser location
- **Distance Calculations**: Shows distance from current location
- **City-Based Organization**: Automatic grouping by cities
- **Map Visualization**: Interactive map view of all favorites
- **Travel Mode**: Easily find favorites when visiting other cities

### User Experience
- **Responsive Design**: Works seamlessly on mobile and desktop
- **Grid/List Views**: Toggle between display modes
- **Real-time Updates**: Instant feedback on all actions
- **Offline Support**: Graceful degradation when offline

### Analytics Dashboard
- **Usage Metrics**: Track total favorites, active users, popular cities
- **Location Patterns**: Understand where users save favorites
- **Business Insights**: See most favorited restaurants
- **Time-based Analytics**: Track growth over time

## Installation

1. Upload the `zippicks-favorites` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. Configure settings under Settings → ZipPicks Favorites

## Requirements

- WordPress 6.0+
- PHP 8.0+
- ZipPicks Core plugin (must be active)
- MySQL with spatial index support

## Configuration

### Map Providers
The plugin supports three map providers:
- **Mapbox** (recommended): Requires API key
- **Google Maps**: Requires API key
- **OpenStreetMap**: Free, no API key required

### Location Settings
- Default search radius: 10km (configurable)
- Maximum search radius: 50km (configurable)
- Geolocation: Enabled by default

## API Endpoints

### REST API
- `POST /wp-json/zippicks/v1/favorites` - Save a favorite
- `DELETE /wp-json/zippicks/v1/favorites/{id}` - Remove a favorite
- `GET /wp-json/zippicks/v1/favorites` - Get user's favorites
- `GET /wp-json/zippicks/v1/favorites/near` - Get favorites near location
- `GET /wp-json/zippicks/v1/favorites/city/{city}` - Get favorites by city
- `GET /wp-json/zippicks/v1/favorites/search` - Search favorites
- `GET /wp-json/zippicks/v1/favorites/cities` - Get user's favorite cities
- `GET /wp-json/zippicks/v1/favorites/export` - Export favorites

## Usage

### For Users
1. Click the heart icon on any business to save it
2. Access favorites from the dashboard or menu
3. Use location filters to find favorites in specific areas
4. Enable "Near Me" to see nearby favorites
5. Export favorites for travel planning

### For Developers
```php
// Check if business is favorited
$is_favorited = zippicks()->get('favorites')->is_favorited($user_id, $business_id);

// Get user's favorites
$favorites = zippicks()->get('favorites')->get_user_favorites($user_id, [
    'city' => 'Los Angeles',
    'per_page' => 20
]);

// Get favorites within radius
$nearby = zippicks()->get('favorites')->get_favorites_within_radius(
    $user_id,
    $latitude,
    $longitude,
    10 // km
);
```

### Shortcodes
```
[zippicks_favorites view="grid" show_map="true" show_filters="true"]
```

## Database Schema

### Tables
- `wp_zippicks_favorites` - Main favorites data
- `wp_zippicks_favorites_meta` - Extended metadata
- `wp_zippicks_location_cache` - Geocoding cache

### Indexes
- User-based queries
- Location-based queries (spatial)
- City/state filtering
- Date-based sorting

## Performance

- Spatial indexes for fast geo queries
- Location caching to minimize API calls
- Optimized queries with proper indexing
- Lazy loading for large datasets

## Hooks & Filters

### Actions
- `zippicks_favorite_saved` - Fired when favorite is saved
- `zippicks_favorite_removed` - Fired when favorite is removed

### Filters
- `zippicks_favorites_per_page` - Modify items per page
- `zippicks_favorites_map_provider` - Change map provider
- `zippicks_favorites_export_format` - Customize export format

## Troubleshooting

### Common Issues

**Map not showing**
- Check if map provider API key is configured
- Ensure JavaScript is enabled
- Check browser console for errors

**Location detection not working**
- HTTPS is required for geolocation
- User must grant location permission
- Check browser compatibility

**Favorites not saving**
- Ensure user is logged in
- Check user capabilities
- Verify database tables exist

## Support

For issues or feature requests, please contact the ZipPicks development team.

## License

Proprietary - All rights reserved by ZipPicks.