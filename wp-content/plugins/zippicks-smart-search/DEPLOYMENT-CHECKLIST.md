# ZipPicks Smart Search - Production Deployment Checklist

## 🚀 Pre-Deployment Verification

### ✅ Code Completeness
- [ ] All files listed in HANDOFF-COMPLETION.md are present
- [ ] No PHP syntax errors: `find . -name "*.php" -exec php -l {} \;`
- [ ] No JavaScript syntax errors: Check browser console
- [ ] All TODO comments resolved or documented
- [ ] Version number updated in main plugin file

### ✅ Security Audit
- [ ] **Rate Limiting**: Verify rate limiter is active on all endpoints
  - Test: `for i in {1..100}; do curl -X GET "https://site.com/wp-json/zippicks/v1/search?q=test"; done`
  - Expected: 429 errors after limit reached
- [ ] **Input Validation**: All user inputs sanitized
  - ✓ Search queries: `sanitize_text_field()`
  - ✓ Location data: Validated lat/lng ranges
  - ✓ Email addresses: `is_email()` validation
- [ ] **XSS Protection**: JavaScript properly escapes HTML
  - ✓ Security helper loaded before main scripts
  - ✓ Template files use proper escaping functions
- [ ] **CSRF Protection**: Nonces verified on all actions
  - ✓ AJAX handlers check nonces
  - ✓ REST endpoints use permission callbacks
- [ ] **SQL Injection**: No direct database queries
  - ✓ All queries use WordPress APIs

### ✅ Performance Testing
- [ ] **Cache Verification**:
  ```bash
  # Check Redis is enabled
  wp eval "var_dump(wp_using_ext_object_cache());"
  ```
- [ ] **Load Testing**: Test with expected traffic
  ```bash
  # Apache Bench test (adjust URL)
  ab -n 1000 -c 10 https://site.com/wp-json/zippicks/v1/search?q=coffee
  ```
- [ ] **Response Times**: All searches < 200ms with cache
- [ ] **Memory Usage**: Monitor with Query Monitor plugin

### ✅ API Integration
- [ ] **PostgreSQL API Connectivity**:
  ```php
  // Test in wp-admin > Tools > Site Health
  $api = new ZipPicks\SmartSearch\API_Client();
  $status = $api->test_connection();
  ```
- [ ] **API Key Configuration**:
  - [ ] Backend API key in wp-config.php: `define('ZIPPICKS_API_KEY', 'key');`
  - [ ] Frontend API key in settings (read-only)
- [ ] **Endpoint Availability**: Verify all required endpoints exist
  - Note: As per handoff, PostgreSQL endpoints need creation

### ✅ Error Handling
- [ ] **Error Tracking Active**: Check admin notices appear
- [ ] **JavaScript Errors**: Verify error reporter loads
- [ ] **PHP Error Log**: No fatal errors in debug.log
- [ ] **External Service Integration** (if configured):
  - [ ] Sentry DSN configured
  - [ ] Rollbar token set
  - [ ] Custom error endpoint tested

## 📋 Deployment Steps

### 1. Environment Preparation
```bash
# Verify PHP version
php -v # Must be 8.0+

# Check WordPress version
wp core version # Must be 6.0+

# Verify Redis/Object Cache
wp plugin list | grep -i cache
```

### 2. Plugin Installation
```bash
# Upload plugin
cd wp-content/plugins/
git clone [repository] zippicks-smart-search

# Set permissions
find zippicks-smart-search -type d -exec chmod 755 {} \;
find zippicks-smart-search -type f -exec chmod 644 {} \;

# Verify file integrity
cd zippicks-smart-search
find . -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
```

### 3. Configuration
```php
// In wp-config.php
define('ZIPPICKS_API_KEY', 'your-api-key');
define('ZIPPICKS_API_URL', 'https://api.zippicks.com');
define('ZIPPICKS_CDN_URL', 'https://cdn.zippicks.com'); // Optional

// For error tracking (optional)
define('ZIPPICKS_SENTRY_DSN', 'your-sentry-dsn');
define('ZIPPICKS_ROLLBAR_TOKEN', 'your-rollbar-token');
```

### 4. Activation & Setup
```bash
# Activate plugin
wp plugin activate zippicks-smart-search

# Verify activation
wp plugin is-active zippicks-smart-search

# Check for database tables (if any)
wp db query "SHOW TABLES LIKE '%zippicks%'"
```

### 5. Initial Configuration
- [ ] Navigate to **ZipPicks Search** in WordPress admin
- [ ] Configure default location settings
- [ ] Set cache TTL values (use defaults initially)
- [ ] Enable/disable autocomplete
- [ ] Configure rate limits if needed
- [ ] Set frontend API key for JavaScript

### 6. Integration Testing
- [ ] Add `[zippicks_search]` shortcode to test page
- [ ] Verify search bar appears and is styled correctly
- [ ] Test search functionality:
  - [ ] Vibe search: "romantic dinner"
  - [ ] Utility search: "Starbucks"
  - [ ] Hybrid search: "best coffee shops"
- [ ] Test autocomplete (if enabled)
- [ ] Test "Coming Soon" notifications
- [ ] Verify mobile responsiveness

### 7. Monitoring Setup
```bash
# Schedule cleanup cron
wp cron event list | grep zippicks

# Verify cache warming (if enabled)
wp cron event run zippicks_warm_search_cache

# Check error log
wp eval "print_r(get_option('zippicks_search_error_log'));"
```

## 🔍 Post-Deployment Verification

### Immediate Checks (First Hour)
- [ ] No 500 errors in server logs
- [ ] Search response times < 500ms
- [ ] Error tracking capturing issues
- [ ] Rate limiting functioning
- [ ] Cache hit rate > 50%

### 24-Hour Checks
- [ ] Review error logs for patterns
- [ ] Check search analytics:
  ```php
  $analytics = new ZipPicks\SmartSearch\Analytics();
  $summary = $analytics->get_summary(24);
  ```
- [ ] Monitor API usage and costs
- [ ] Review demand tracking data
- [ ] Check memory usage trends

### Weekly Monitoring
- [ ] Cache hit rate > 80%
- [ ] Average response time < 200ms
- [ ] Error rate < 0.1%
- [ ] Search-to-click rate > 30%
- [ ] API uptime > 99.9%

## 🚨 Rollback Plan

### If Critical Issues Occur:
```bash
# 1. Deactivate plugin immediately
wp plugin deactivate zippicks-smart-search

# 2. Clear caches
wp cache flush

# 3. Check error logs
tail -f wp-content/debug.log

# 4. If needed, remove plugin
mv zippicks-smart-search zippicks-smart-search.backup

# 5. Notify team and investigate
```

## 📊 Success Metrics

### Technical Metrics
- **Performance**: 95th percentile response time < 300ms
- **Availability**: 99.9% uptime
- **Error Rate**: < 0.1% of requests
- **Cache Hit Rate**: > 80%

### Business Metrics
- **Search Usage**: Track unique searches per day
- **Conversion**: Search-to-business-view rate
- **Engagement**: Autocomplete usage rate
- **Demand**: Coming soon notification signups

## 📞 Support Contacts

### During Deployment
- Lead Engineer: [Contact]
- DevOps Team: [Contact]
- API Team: [Contact for PostgreSQL endpoints]

### Post-Deployment
- Monitor via: WordPress admin > ZipPicks Search > Analytics
- Error alerts sent to: [configured email]
- Escalation: [escalation process]

## ✅ Final Signoff

- [ ] All checklist items completed
- [ ] Stakeholders notified
- [ ] Documentation updated
- [ ] Monitoring active
- [ ] Team trained on plugin usage

**Deployment Date**: _______________
**Deployed By**: _______________
**Version Deployed**: 1.0.0
**Notes**: _____________________