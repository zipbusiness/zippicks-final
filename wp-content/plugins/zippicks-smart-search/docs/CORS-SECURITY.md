# CORS Security Configuration for ZipPicks Smart Search

## Overview

When using the ZipPicks Smart Search plugin with a frontend API key, it's crucial to implement proper CORS (Cross-Origin Resource Sharing) restrictions on your API server to prevent unauthorized use of your API key.

## Security Notice

**⚠️ Important**: Frontend API keys are publicly visible in the browser's source code. Always ensure:
- The key is restricted to READ-ONLY operations
- Proper CORS/domain restrictions are configured
- The key cannot access sensitive data or perform write operations

## Recommended CORS Configuration

### 1. Domain Restrictions

Configure your API server to only accept requests from your WordPress domain:

```nginx
# Nginx Example
location /api/ {
    if ($http_origin ~* (https?://(www\.)?yourdomain\.com)) {
        add_header 'Access-Control-Allow-Origin' '$http_origin';
    }
}
```

```apache
# Apache Example
<IfModule mod_headers.c>
    SetEnvIf Origin "https?://(www\.)?yourdomain\.com$" AccessControlAllowOrigin=$0
    Header set Access-Control-Allow-Origin %{AccessControlAllowOrigin}e env=AccessControlAllowOrigin
</IfModule>
```

### 2. API Key Restrictions

Implement these restrictions for frontend API keys:

1. **Read-Only Access**: Frontend keys should only have GET permissions
2. **Endpoint Restrictions**: Limit access to specific search endpoints only
3. **Rate Limiting**: Implement per-key rate limits (e.g., 100 requests/minute)
4. **IP Allowlisting**: Optional - restrict to your server's IP range

### 3. Secure Proxy Option (Recommended)

For enhanced security, use the built-in proxy option:

1. Enable "Use secure proxy for API calls" in plugin settings
2. Configure a separate backend API key with full permissions
3. Frontend requests will route through your WordPress server
4. API key is never exposed to the browser

## API Server Configuration Examples

### Express.js (Node.js)
```javascript
const cors = require('cors');

app.use(cors({
    origin: ['https://yourdomain.com', 'https://www.yourdomain.com'],
    credentials: true,
    methods: ['GET'],
    allowedHeaders: ['Content-Type', 'X-API-Key']
}));

// Validate API key permissions
app.use((req, res, next) => {
    const apiKey = req.headers['x-api-key'];
    if (apiKey && isFrontendKey(apiKey)) {
        // Only allow GET requests for frontend keys
        if (req.method !== 'GET') {
            return res.status(403).json({ error: 'Read-only access' });
        }
    }
    next();
});
```

### PHP API Server
```php
// CORS headers
$allowed_origins = ['https://yourdomain.com', 'https://www.yourdomain.com'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Methods: GET");
    header("Access-Control-Allow-Headers: Content-Type, X-API-Key");
}

// Validate frontend API key
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (is_frontend_key($api_key) && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(403);
    die(json_encode(['error' => 'Read-only access']));
}
```

## Best Practices

1. **Separate Keys**: Use different API keys for frontend vs backend operations
2. **Key Rotation**: Rotate API keys regularly (every 90 days)
3. **Monitor Usage**: Track API key usage for anomalies
4. **HTTPS Only**: Always use HTTPS for API communications
5. **Error Handling**: Don't expose internal errors to frontend

## Troubleshooting

### CORS Errors
If you see CORS errors in the browser console:
1. Verify your domain is whitelisted on the API server
2. Check that HTTPS is used consistently
3. Ensure headers are sent before any output

### API Key Issues
1. Verify the key has proper read permissions
2. Check rate limits haven't been exceeded
3. Ensure the key is active and not expired

## Security Checklist

- [ ] Frontend API key is read-only
- [ ] CORS configured to allow only your domain
- [ ] HTTPS enforced on both WordPress and API server
- [ ] Rate limiting implemented
- [ ] API key rotation schedule in place
- [ ] Monitoring/logging enabled for API usage
- [ ] Consider using proxy mode for sensitive data

## Need Help?

For additional security guidance or implementation help, consult your API provider's documentation or contact ZipPicks support.