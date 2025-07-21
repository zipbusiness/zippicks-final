# ZipPicks Taste Graph Connector

WordPress plugin that connects to the ZipBusiness Taste Graph API to provide personalized restaurant recommendations based on user behavior and preferences.

## Features

- **Anonymous Session Tracking**: Tracks user interactions before login
- **Session Linking**: Automatically links anonymous sessions to user accounts on login
- **Interaction Tracking**: Monitors restaurant views, clicks, saves, and vibe preferences
- **JWT Authentication**: Secure communication with the FastAPI backend
- **Queue Management**: Redis and database fallback for reliable data delivery
- **Admin Dashboard**: Configuration and monitoring interface

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Composer (for dependency management)
- Redis (optional, for improved performance)

## Installation

1. Upload the `taste-graph-connector` folder to `/wp-content/plugins/`
2. Install dependencies:
   ```bash
   cd wp-content/plugins/taste-graph-connector
   composer install --no-dev
   ```
3. Activate the plugin through the WordPress admin panel
4. Configure the plugin under **Taste Graph** menu

## Configuration

### Required Settings

1. **API URL**: Your ZipBusiness API endpoint (e.g., `https://api.zipbusiness.com/v1`)
2. **API Key**: Authentication key for the API
3. **JWT Secret**: Shared secret for token generation (must match API configuration)

### Optional Settings

- **Redis Configuration**: For improved queue performance
- **Tracking Options**: Control which interactions to track
- **Debug Mode**: Enable detailed logging

## Usage

### Frontend Integration

Add data attributes to elements you want to track:

```html
<!-- Track restaurant clicks -->
<a href="/restaurant/123" data-tgc-restaurant="zp_12345678">
  Restaurant Name
</a>

<!-- Track vibe selections -->
<button data-tgc-vibe="5">Romantic</button>

<!-- Track saves -->
<button data-tgc-save="zp_12345678" class="save-btn">
  Save Restaurant
</button>

<!-- Track searches -->
<form data-tgc-search>
  <input type="search" name="q">
  <button type="submit">Search</button>
</form>
```

### JavaScript API

Access the tracker instance:

```javascript
// Get user's taste profile (logged-in users only)
window.TasteGraphTracker.getTasteProfile()
  .then(profile => {
    console.log('User preferences:', profile);
  });

// Manual tracking
window.TasteGraphTracker.track('custom_event', {
  custom_data: 'value'
});
```

## Development

### Running Tests

```bash
composer test
```

### Code Standards

```bash
composer phpcs
composer phpcbf  # Auto-fix issues
```

## Hooks and Filters

### Actions

- `tgc_before_track_interaction` - Before tracking an interaction
- `tgc_after_track_interaction` - After tracking an interaction
- `tgc_session_linked` - When anonymous session is linked to user

### Filters

- `tgc_tracking_enabled` - Control tracking per request
- `tgc_interaction_data` - Modify interaction data before sending
- `tgc_api_timeout` - Customize API timeout

## Troubleshooting

### API Connection Issues

1. Check API URL and credentials in settings
2. Use "Test Connection" button to verify
3. Check debug logs if enabled

### Queue Processing

- Monitor queue status in admin dashboard
- Use "Process Queue Now" for manual processing
- Check cron job is running: `wp cron event list`

### Session Tracking

- Verify session cookies are being set
- Check browser console for JavaScript errors
- Enable debug mode for detailed logging

## Support

For issues and feature requests, please contact support@zipbusiness.com