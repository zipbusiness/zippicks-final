# Taste Graph Connector - Testing Setup Guide

## Quick Setup for Testing

### 1. Configure WordPress to Use Test API

Add these lines to your `wp-config.php` file:

```php
// Taste Graph Connector Test Configuration
define('TGC_API_URL_OVERRIDE', 'https://zipbusiness-api-test.onrender.com');
define('TGC_JWT_SECRET_OVERRIDE', 'your-test-jwt-secret-here');
define('TGC_DEBUG_MODE', true);
```

### 2. Switch Between Test and Production

**For Testing:**
```php
define('TGC_API_URL_OVERRIDE', 'https://zipbusiness-api-test.onrender.com');
```

**For Production:**
```php
// Comment out or remove this line to use production
// define('TGC_API_URL_OVERRIDE', 'https://zipbusiness-api-test.onrender.com');
```

### 3. Verify Current Configuration

1. Go to WordPress Admin → Taste Graph → Settings
2. Check the "API URL" field - it should show your test URL
3. Click "Test Connection" to verify

### 4. Environment Variables for Test API

Set these in your Render test service:

```
JWT_SECRET_KEY=your-test-jwt-secret-here
DATABASE_URL=(copy from production)
REDIS_URL=(copy from production)
ENVIRONMENT=test
```

### 5. Testing Checklist

- [ ] Test API health endpoint: `https://zipbusiness-api-test.onrender.com/health`
- [ ] Test WordPress health endpoint: `https://zipbusiness-api-test.onrender.com/wp/health`
- [ ] Enable debug mode in plugin settings
- [ ] Check browser console for tracking events
- [ ] Verify API requests in Network tab

### 6. Rollback Plan

If issues occur, simply comment out the override in wp-config.php:
```php
// define('TGC_API_URL_OVERRIDE', 'https://zipbusiness-api-test.onrender.com');
```

The plugin will immediately revert to the production API.