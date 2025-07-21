# ZipPicks Vibes API Middleware Documentation

## Overview

The ZipPicks Vibes plugin includes two critical middleware components for protecting REST API endpoints:

1. **RateLimiter** - Controls request frequency and prevents abuse
2. **NonceValidator** - Ensures request authenticity and prevents CSRF attacks

Both components implement the strict anti-scraping policy defined in CLAUDE.md.

## RateLimiter

### Features

- **IP-based and User-based Rate Limiting** - Tracks requests per IP or authenticated user
- **User-Agent Validation** - Blocks CLI tools and scraping bots
- **Anti-Scraping Headers** - Automatically adds required security headers
- **Scrape Detection** - Identifies and blocks scraping patterns
- **Automatic Banning** - Temporarily bans IPs showing malicious behavior
- **Whitelisting** - Supports IP ranges, specific IPs, and user roles
- **Database Logging** - Tracks all rate limit violations and scraping attempts

### Usage

```php
// Initialize rate limiter
$rate_limiter = new \ZipPicksVibesV2\Api\Middleware\RateLimiter($cache, $logger);

// In your REST API callback
add_action('rest_api_init', function() {
    register_rest_route('zippicks/v1', '/vibes', [
        'methods' => 'GET',
        'callback' => 'get_vibes_handler',
        'permission_callback' => function($request) use ($rate_limiter) {
            // Check rate limit
            if (!$rate_limiter->check($request)) {
                return $rate_limiter->getErrorResponse($request);
            }
            return true;
        }
    ]);
});

// Add rate limit headers to response
add_filter('rest_post_dispatch', function($response, $server, $request) use ($rate_limiter) {
    $headers = array_merge(
        $rate_limiter->getHeaders($request),
        $rate_limiter->getAntiScrapingHeaders()
    );
    
    foreach ($headers as $key => $value) {
        $response->header($key, $value);
    }
    
    return $response;
}, 10, 3);
```

### Configuration

```php
// Set custom limits for specific endpoints
$rate_limiter->setLimit('/zippicks/v1/vibes/search', 30, 3600); // 30 requests per hour

// Update global limits
$rate_limiter->updateGlobalLimits([
    'default' => ['requests' => 100, 'window' => 3600],
    'authenticated' => ['requests' => 500, 'window' => 3600]
]);

// Whitelist IPs or roles
$rate_limiter->addToWhitelist('192.168.1.100');
$rate_limiter->addRoleToWhitelist('administrator');

// Ban an IP manually
$rate_limiter->banIP('1.2.3.4', 86400, 'Manual ban for abuse');
```

### Anti-Scraping Features

The RateLimiter automatically:

1. **Rejects Invalid User-Agents** - Blocks curl, wget, python, and other CLI tools
2. **Logs to zippicks_scrape_log** - As required by anti-scraping policy
3. **Detects Patterns** - Auto-bans IPs making >10 requests/minute to list endpoints
4. **Adds Security Headers**:
   - `X-Robots-Tag: noindex`
   - `Cache-Control: private, max-age=0`
   - `X-ZipPicks-Source: frontend-only`

## NonceValidator

### Features

- **Session-Bound Nonces** - Ties nonces to user sessions for enhanced security
- **Multiple Source Support** - Checks headers, parameters, and JSON body
- **Public Route Configuration** - Define endpoints that don't require nonces
- **Security Checks** - SQL injection, XSS, and path traversal detection
- **Capability-Based Access** - Enforce permissions for protected endpoints
- **Comprehensive Logging** - Tracks all validation failures

### Usage

```php
// Initialize validator
$nonce_validator = new \ZipPicksVibesV2\Api\Middleware\NonceValidator('wp_rest', $logger, $audit_logger);

// In your REST API callback
register_rest_route('zippicks/v1', '/vibes/(?P<id>\d+)', [
    'methods' => 'PUT',
    'callback' => 'update_vibe_handler',
    'permission_callback' => function($request) use ($nonce_validator) {
        $validation = $nonce_validator->validate($request);
        if (!$validation['success']) {
            return new WP_Error('invalid_nonce', $validation['reason'], ['status' => 403]);
        }
        return true;
    }
]);

// Generate nonce for frontend
$nonce = $nonce_validator->createNonce(); // Session-bound nonce

// For rotating nonces (extra security)
$rotating_nonce = $nonce_validator->generateRotatingNonce();
```

### Configuration

```php
// Add public routes (no nonce required)
$nonce_validator->addPublicRoute('GET', '/zippicks/v1/vibes');
$nonce_validator->addPublicRoute('GET', '/zippicks/v1/vibes/search');

// Generate nonce field for forms
echo $nonce_validator->getNonceField(); // Outputs hidden input with nonce
```

### Session Binding

The NonceValidator implements session binding as required by the anti-scraping policy:

1. **Nonces are tied to PHP sessions** - Prevents token reuse across sessions
2. **IP verification** - Detects if nonce is used from different IP
3. **Time-based rotation** - Nonces expire and rotate automatically

## Integration Example

```php
// Complete REST API endpoint with both middlewares
add_action('rest_api_init', function() {
    $rate_limiter = new \ZipPicksVibesV2\Api\Middleware\RateLimiter();
    $nonce_validator = new \ZipPicksVibesV2\Api\Middleware\NonceValidator();
    
    register_rest_route('zippicks/v1', '/vibes/create', [
        'methods' => 'POST',
        'callback' => 'create_vibe_handler',
        'permission_callback' => function($request) use ($rate_limiter, $nonce_validator) {
            // Check rate limit first
            if (!$rate_limiter->check($request)) {
                return $rate_limiter->getErrorResponse($request);
            }
            
            // Then validate nonce
            $validation = $nonce_validator->validate($request);
            if (!$validation['success']) {
                return new WP_Error('security_check_failed', $validation['reason'], ['status' => 403]);
            }
            
            // Check user capabilities
            if (!current_user_can('manage_options')) {
                return new WP_Error('insufficient_permissions', 'You need admin access', ['status' => 403]);
            }
            
            return true;
        },
        'args' => [
            'name' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ]
    ]);
});

// Add security headers to all API responses
add_filter('rest_post_dispatch', function($response, $server, $request) {
    $rate_limiter = new \ZipPicksVibesV2\Api\Middleware\RateLimiter();
    
    // Add rate limit headers
    $headers = $rate_limiter->getHeaders($request);
    foreach ($headers as $key => $value) {
        $response->header($key, $value);
    }
    
    // Add anti-scraping headers
    $anti_scraping = $rate_limiter->getAntiScrapingHeaders();
    foreach ($anti_scraping as $key => $value) {
        $response->header($key, $value);
    }
    
    return $response;
}, 10, 3);
```

## Monitoring & Maintenance

### Rate Limiter Statistics

```php
// Get rate limit statistics
$stats = $rate_limiter->getStatistics('', 24); // Last 24 hours
echo "Total rate limit hits: " . $stats['total_hits'];
echo "Top offenders: " . print_r($stats['top_offenders'], true);

// Clean up old logs (run via cron)
$rate_limiter->cleanup(); // Removes logs older than 7 days
```

### Security Event Monitoring

```php
// Get security statistics
$security_stats = $nonce_validator->getStatistics(48); // Last 48 hours
echo "Failed validations: " . $security_stats['total_failures'];
echo "Unique IPs with failures: " . $security_stats['unique_ips'];

// Clean up old security logs
$nonce_validator->cleanupLogs(30); // Keep 30 days of logs
```

## Database Tables

Both middlewares create and maintain their own database tables:

### Rate Limiter Tables
- `{prefix}zippicks_rate_limit_log` - Tracks all rate limit violations
- `{prefix}zippicks_ip_bans` - Stores temporary IP bans
- `{prefix}zippicks_scrape_log` - Anti-scraping watchdog log

### Nonce Validator Tables
- `{prefix}zippicks_security_log` - Comprehensive security event log

## Compliance with Anti-Scraping Policy

Both middlewares implement all requirements from CLAUDE.md:

✅ **Session-bound nonces** - NonceValidator ties all nonces to sessions
✅ **Request logging** - Both log IP, endpoint, headers to database
✅ **Rate limiting by user type** - Different limits for public/authenticated/admin
✅ **User-Agent validation** - Rejects empty or CLI agents
✅ **Required headers** - X-Robots-Tag, Cache-Control, X-ZipPicks-Source
✅ **Per-IP metering** - Detects and throttles abnormal patterns
✅ **Scrape watchdog** - Dedicated logging table with pattern detection
✅ **Auto-banning** - Temporarily bans IPs showing scraping behavior

## Best Practices

1. **Always use both middlewares** for protected endpoints
2. **Configure public routes explicitly** to avoid blocking legitimate traffic
3. **Monitor logs regularly** for suspicious patterns
4. **Set up cron jobs** for log cleanup
5. **Adjust rate limits** based on your traffic patterns
6. **Keep whitelist minimal** - Only trusted IPs and roles
7. **Test thoroughly** with different user agents and scenarios

## Troubleshooting

### Common Issues

**"Rate limit exceeded" for legitimate users**
- Check if their IP is in a shared network
- Consider whitelisting authenticated user roles
- Adjust limits for specific endpoints

**"Invalid nonce" errors**
- Ensure sessions are enabled on your server
- Check if caching plugins are interfering
- Verify nonce is being sent in correct header/parameter

**Scraping still occurring**
- Review scrape_log table for patterns
- Tighten rate limits
- Add more User-Agent blocks
- Consider implementing CAPTCHA for suspicious requests

### Debug Mode

Enable debug logging:
```php
define('ZIPPICKS_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

This will log additional details about rate limiting and nonce validation decisions.