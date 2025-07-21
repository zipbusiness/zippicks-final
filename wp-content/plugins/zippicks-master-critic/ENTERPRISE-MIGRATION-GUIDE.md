# ZipPicks Master Critic - Enterprise Migration Guide

## Overview

The Master Critic plugin has been upgraded with enterprise-ready features to ensure optimal performance, security, and reliability at scale. This guide covers the migration process and new features.

## What's New in Enterprise Edition

### 1. **PHP 8.2+ Compatibility**
- Full compatibility with PHP 8.2+ including proper property declarations
- `#[AllowDynamicProperties]` attribute for backward compatibility
- Typed properties for better code quality and IDE support

### 2. **Enhanced Logging System**
- PSR-3 compliant logging with multiple log levels
- Automatic log rotation and cleanup
- Structured JSON logging for better parsing
- Sensitive data redaction
- Integration with external monitoring services

### 3. **Advanced Caching System**
- Multi-backend support (Redis, APCu, Object Cache, Transients)
- Automatic backend detection and failover
- Cache warming and preloading
- Performance metrics and hit rate tracking

### 4. **Enterprise Security**
- Enhanced nonce verification with timestamp validation
- Comprehensive input sanitization
- Rate limiting with configurable thresholds
- IP blocking and threat detection
- Security event logging and alerts
- Encryption for sensitive data

### 5. **Health Monitoring**
- Real-time health checks for all components
- Performance metrics collection
- Automated alerts for critical issues
- REST API endpoint for external monitoring
- Detailed health reports

### 6. **Performance Optimization**
- Request performance tracking
- Slow query detection
- API call monitoring
- Memory usage optimization
- Performance recommendations engine

## Migration Steps

### Step 1: Backup Your Site
```bash
# Backup database
wp db export backup-before-enterprise.sql

# Backup files
tar -czf master-critic-backup.tar.gz wp-content/plugins/zippicks-master-critic/
```

### Step 2: Check Requirements
- PHP 8.0 or higher (8.2+ recommended)
- WordPress 6.0 or higher
- MySQL 5.7+ or MariaDB 10.2+
- 256MB+ PHP memory limit

### Step 3: Update Plugin Files
The plugin will automatically use enterprise features when available. No manual file changes required.

### Step 4: Run Migration Tests
```bash
# Via WP-CLI
wp eval-file wp-content/plugins/zippicks-master-critic/test-enterprise-features.php

# Or via browser
yoursite.com/wp-content/plugins/zippicks-master-critic/test-enterprise-features.php
```

### Step 5: Configure Enterprise Features

#### Enable Logging
```php
// In wp-config.php
define('ZIPPICKS_LOG_LEVEL', 'info'); // Options: debug, info, warning, error
```

#### Configure Cache Backend
```php
// For Redis
define('WP_REDIS_HOST', '127.0.0.1');
define('WP_REDIS_PORT', 6379);

// For APCu (enable in php.ini)
apc.enabled=1
apc.enable_cli=1
```

#### Set Security Options
Navigate to **Settings > Master Critic > Security** to configure:
- Rate limiting thresholds
- IP whitelist/blacklist
- Security alert email

### Step 6: Monitor Health
1. Access health dashboard: `/wp-admin/admin.php?page=zippicks-master-critic-health`
2. Set up external monitoring using the health check endpoint
3. Review performance recommendations

## Configuration Options

### Environment Variables
```bash
# .env file
ZIPPICKS_LOG_LEVEL=info
ZIPPICKS_CACHE_BACKEND=redis
ZIPPICKS_MONITORING_ENDPOINT=https://your-monitoring.com/api
ZIPPICKS_RATE_LIMIT_WINDOW=60
ZIPPICKS_MAX_REQUESTS=30
```

### WordPress Options
```php
// Programmatic configuration
update_option('zippicks_log_level', 'info');
update_option('zippicks_cache_ttl', 3600);
update_option('zippicks_enable_monitoring', true);
```

## Troubleshooting

### Issue: "Class not found" errors
**Solution**: Ensure all new files are uploaded and file permissions are correct.

### Issue: Performance degradation
**Solution**: 
1. Check cache backend status
2. Review slow query log
3. Increase PHP memory limit
4. Enable Redis/APCu if not already

### Issue: Security blocks legitimate users
**Solution**:
1. Review security log for false positives
2. Adjust rate limiting thresholds
3. Whitelist trusted IPs

### Issue: Logs not being created
**Solution**:
1. Check write permissions on upload directory
2. Verify log directory exists: `/wp-content/uploads/zippicks-logs/`
3. Ensure `ZIPPICKS_DEBUG` is enabled

## Performance Tuning

### Recommended Settings
```php
// wp-config.php
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '1024M');
define('DISABLE_WP_CRON', true); // Use system cron instead
```

### Cache Configuration
```php
// For optimal performance
add_filter('zippicks_cache_ttl', function($ttl) {
    return 86400; // 24 hours
});
```

### Database Optimization
```sql
-- Add indexes for better performance
ALTER TABLE wp_zippicks_generations ADD INDEX idx_status_created (status, created_at);
ALTER TABLE wp_zippicks_api_usage ADD INDEX idx_created (created_at);
```

## API Changes

### New Hooks

#### Filters
- `zippicks_use_enterprise_features` - Enable/disable enterprise features
- `zippicks_log_level` - Set minimum log level
- `zippicks_cache_backend` - Force specific cache backend
- `zippicks_rate_limit_threshold` - Adjust rate limits

#### Actions
- `zippicks_health_critical` - Fired on critical health issues
- `zippicks_security_alert` - Fired on security events
- `zippicks_performance_issue` - Fired on performance problems

### New Functions
```php
// Get health status
$health = ZipPicks_Master_Critic_Enterprise::get_instance()->get_health_check();
$status = $health->get_status();

// Log custom events
$logger = ZipPicks_Master_Critic_Enterprise::get_instance()->get_logger();
$logger->info('Custom event', ['data' => $data]);

// Cache operations
$cache = ZipPicks_Master_Critic_Enterprise::get_instance()->get_cache_manager();
$value = $cache->remember('key', function() {
    return expensive_operation();
}, 3600);
```

## Best Practices

1. **Always use caching** for expensive operations
2. **Log important events** for debugging and auditing
3. **Monitor health status** regularly
4. **Review security logs** weekly
5. **Update API keys** securely through the admin interface
6. **Test in staging** before deploying to production

## Support

For enterprise support:
- Email: support@zippicks.com
- Documentation: https://docs.zippicks.com/master-critic/enterprise
- Emergency: Use the health check endpoint for immediate status

## Rollback Procedure

If you need to rollback:

1. Restore from backup:
```bash
wp db import backup-before-enterprise.sql
tar -xzf master-critic-backup.tar.gz -C /
```

2. Disable enterprise features:
```php
add_filter('zippicks_use_enterprise_features', '__return_false');
```

3. Clear all caches:
```bash
wp cache flush
```

## Changelog

### Version 1.0.0 (Enterprise)
- Added PHP 8.2+ compatibility
- Implemented PSR-3 logging
- Added multi-backend caching
- Enhanced security features
- Added health monitoring
- Implemented performance tracking
- Improved error handling
- Added Foundation integration

---

*Last updated: June 2024*