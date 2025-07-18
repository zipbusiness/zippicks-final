# ZipPicks Vibes Admin User Guide

> **Version:** 2.0.0  
> **Last Updated:** June 2025

## Table of Contents

1. [Overview](#overview)
2. [Getting Started](#getting-started)
3. [Dashboard Overview](#dashboard-overview)
4. [Managing Vibes](#managing-vibes)
5. [Monitoring Dashboard](#monitoring-dashboard)
6. [Security Features](#security-features)
7. [API Integration](#api-integration)
8. [Performance Optimization](#performance-optimization)
9. [Troubleshooting](#troubleshooting)
10. [Best Practices](#best-practices)

---

## Overview

The ZipPicks Vibes plugin is the core discovery engine for your local business platform. It enables mood-based discovery through an innovative "vibe" taxonomy system that goes beyond traditional categories.

### Key Features

- **Vibe Management**: Create and manage mood-based taxonomies
- **Smart Search**: ZIP-aware search with autocomplete
- **Real-time Monitoring**: Enterprise-grade performance tracking
- **Security First**: Built-in anti-scraping and rate limiting
- **API Access**: RESTful API for mobile and third-party integrations

### Who Should Use This Guide

- WordPress administrators managing the ZipPicks platform
- Content managers creating and organizing vibes
- Technical staff monitoring system performance
- API developers integrating with the platform

---

## Getting Started

### Prerequisites

Before using the ZipPicks Vibes plugin, ensure:

1. **ZipPicks Foundation** is installed and active
2. WordPress version 6.0 or higher
3. PHP 8.0 or higher
4. Proper user permissions (manage_zippicks_vibes capability)

### Initial Setup

1. Navigate to **ZipPicks → Settings**
2. Configure these essential settings:
   - **Cache Duration**: Default 3600 seconds (1 hour)
   - **Rate Limits**: API request limits per minute/hour
   - **Security Settings**: Anti-scraping protection levels

### User Permissions

The plugin introduces these capabilities:
- `manage_zippicks_vibes` - Full vibe management access
- `view_zippicks_monitoring` - Access to monitoring dashboard
- `access_zippicks_api` - API usage permissions

---

## Dashboard Overview

### Accessing the Dashboard

Navigate to **ZipPicks → Dashboard** in your WordPress admin menu.

### Dashboard Components

#### 1. Quick Stats
- Total active vibes
- Popular vibes this week
- Recent vibe additions
- API usage statistics

#### 2. Activity Feed
- Recent vibe creations
- Popular search terms
- System health alerts
- Security events

#### 3. Quick Actions
- Add New Vibe
- View Monitoring
- Export Data
- Clear Cache

---

## Managing Vibes

### Understanding Vibes

Vibes are mood-based categories that help users discover businesses based on feelings and experiences rather than traditional categories.

**Examples:**
- "Date Night" instead of "Romantic Restaurants"
- "Work From Here" instead of "Cafes with WiFi"
- "Natural Wine" instead of "Wine Bars"

### Creating a New Vibe

1. Navigate to **ZipPicks → Vibes → Add New**
2. Fill in the required fields:

#### Basic Information
- **Name**: The vibe's display name (e.g., "Cozy Corners")
- **Slug**: URL-friendly version (auto-generated)
- **Description**: Detailed explanation of the vibe
- **Category**: Parent category (Master Vibes)

#### Advanced Settings
- **Icon**: Visual representation (emoji or icon class)
- **Color**: Brand color for the vibe (#hex)
- **Priority**: Display order (1-100)
- **Status**: Active, Inactive, or Seasonal

#### SEO Settings
- **Meta Title**: SEO-optimized title
- **Meta Description**: Search engine description
- **Keywords**: Related search terms

### Editing Existing Vibes

1. Go to **ZipPicks → Vibes**
2. Click on the vibe name or "Edit"
3. Update necessary fields
4. Click "Update Vibe"

### Bulk Operations

Select multiple vibes and choose:
- **Activate/Deactivate**: Toggle vibe status
- **Change Category**: Move to different parent
- **Export**: Download vibe data
- **Delete**: Permanently remove (with confirmation)

### Vibe Categories (Master Vibes)

The 10 master vibe categories:
1. **Social** - Group experiences
2. **Solo** - Individual activities
3. **Cultural** - Arts and heritage
4. **Adventure** - Active experiences
5. **Wellness** - Health and relaxation
6. **Luxury** - Premium experiences
7. **Budget** - Affordable options
8. **Family** - Kid-friendly
9. **Professional** - Business-oriented
10. **Unique** - One-of-a-kind experiences

---

## Monitoring Dashboard

### Accessing Monitoring

Navigate to **ZipPicks → Monitoring** for real-time system insights.

### Key Metrics

#### System Health
- **Database Status**: Connection health and query performance
- **Cache Status**: Hit rate and memory usage
- **API Status**: Endpoint availability
- **Security Status**: Threat detection status

#### Performance Metrics
- **Average Response Time**: API and page load speeds
- **Request Volume**: Hourly/daily traffic patterns
- **Slow Queries**: Database optimization opportunities
- **Error Rate**: System stability indicator

### Understanding Charts

#### Response Time Trend
Shows API response times over the selected period:
- **Green Zone** (0-200ms): Excellent
- **Yellow Zone** (200-500ms): Acceptable
- **Red Zone** (500ms+): Needs attention

#### Request Volume
Displays API usage by endpoint:
- Identify popular endpoints
- Spot usage patterns
- Plan capacity needs

### Health Checks

Click "Run Health Check" to verify:
- Database connectivity
- Cache availability
- File permissions
- API endpoints
- Security systems

### Audit Logs

Filter and review system activities:
- **Event Types**: Create, Update, Delete, Security
- **Severity Levels**: Info, Warning, Error, Critical
- **User Actions**: Track who did what and when

---

## Security Features

### Anti-Scraping Protection

The plugin implements multiple layers of protection:

1. **Rate Limiting**
   - Per-IP request limits
   - Progressive delays for violations
   - Automatic blocking for severe abuse

2. **Session Validation**
   - Required tokens for API access
   - Nonce verification for all actions
   - Session-bound requests

3. **Content Protection**
   - Dynamic content loading
   - Obfuscated HTML structure
   - Invisible watermarks

### Managing Security Events

1. Go to **Monitoring → Security Events**
2. Review flagged activities:
   - Excessive requests
   - Invalid tokens
   - Suspicious patterns
3. Take action:
   - Block IP addresses
   - Adjust rate limits
   - Enable stricter validation

### Security Best Practices

- Regularly review audit logs
- Monitor rate limit violations
- Keep WordPress and plugins updated
- Use strong passwords and 2FA
- Limit API access to trusted applications

---

## API Integration

### API Overview

The Vibes API provides programmatic access to:
- Vibe listings and details
- Search functionality
- Popular vibes
- Autocomplete suggestions

### Endpoints

Base URL: `https://yoursite.com/wp-json/zippicks/v2/`

#### Available Endpoints

1. **Get All Vibes**
   ```
   GET /vibes
   ```

2. **Get Single Vibe**
   ```
   GET /vibes/{id}
   ```

3. **Search Vibes**
   ```
   GET /vibes/search?q={query}&zip={zipcode}
   ```

4. **Popular Vibes**
   ```
   GET /vibes/popular?limit={number}
   ```

5. **Autocomplete**
   ```
   GET /vibes/autocomplete?q={query}
   ```

### Authentication

Include these headers:
```
X-WP-Nonce: {nonce_value}
X-ZipPicks-Session: {session_token}
```

### Rate Limits

- **Public Access**: 60 requests/hour
- **Authenticated**: 300 requests/hour
- **Premium**: 1000 requests/hour

---

## Performance Optimization

### Cache Management

#### Viewing Cache Status
1. Go to **Monitoring → System Info**
2. Check "Cache Hit Rate"
3. Target: Above 80%

#### Clearing Cache
- **Selective Clear**: Specific vibe or endpoint
- **Full Clear**: All cached data (use sparingly)

### Database Optimization

#### Identifying Issues
1. Check "Slow Queries" in monitoring
2. Look for queries over 100ms
3. Note frequently slow operations

#### Optimization Steps
1. Run database optimization (monthly)
2. Clean old audit logs (Settings → Maintenance)
3. Index custom tables if needed

### CDN Integration

For optimal performance:
1. Serve static assets via CDN
2. Cache API responses at edge
3. Use geographic distribution

---

## Troubleshooting

### Common Issues

#### Vibes Not Appearing
**Symptoms**: Created vibes don't show in searches

**Solutions**:
1. Check vibe status (must be "Active")
2. Clear cache
3. Verify permissions
4. Check error logs

#### Slow Performance
**Symptoms**: Pages load slowly, timeouts

**Solutions**:
1. Review monitoring dashboard
2. Identify slow queries
3. Increase cache duration
4. Check server resources

#### API Errors
**Symptoms**: 4xx or 5xx errors from API

**Solutions**:
1. Verify authentication headers
2. Check rate limit status
3. Review API logs
4. Test with minimal parameters

### Getting Help

1. **Check Logs**: WordPress debug.log
2. **Monitoring**: Built-in diagnostics
3. **Support**: support@zippicks.com
4. **Documentation**: docs.zippicks.com

---

## Best Practices

### Vibe Creation

1. **Naming Conventions**
   - Use descriptive, searchable names
   - Avoid duplicate concepts
   - Consider local language/slang

2. **Categorization**
   - Place vibes in appropriate master categories
   - Don't over-categorize
   - Think user-first

3. **Descriptions**
   - Write for both users and SEO
   - Include examples
   - Keep concise but complete

### System Maintenance

#### Daily Tasks
- Review monitoring dashboard
- Check for security alerts
- Monitor API usage

#### Weekly Tasks
- Review popular searches
- Update trending vibes
- Check system health

#### Monthly Tasks
- Analyze usage patterns
- Optimize slow queries
- Clean up old data
- Review and update vibes

### Performance Tips

1. **Use Caching Wisely**
   - Longer cache for stable data
   - Shorter for dynamic content
   - Clear selectively

2. **Monitor Regularly**
   - Set up alerts for issues
   - Review trends, not just current state
   - Act on insights

3. **Scale Gradually**
   - Add vibes systematically
   - Monitor impact of changes
   - Plan for growth

---

## Appendix

### Keyboard Shortcuts

- `Alt + V`: Quick add vibe
- `Alt + M`: Open monitoring
- `Alt + C`: Clear cache
- `Alt + S`: Save changes

### Glossary

- **Vibe**: Mood-based category for discovery
- **Master Vibe**: Top-level category
- **Cache Hit Rate**: Percentage of cached responses
- **Nonce**: Security token for requests
- **Rate Limiting**: Request throttling for protection

### Additional Resources

- [API Documentation](./API-DOCUMENTATION.md)
- [Developer Guide](./DEVELOPER-GUIDE.md)
- [Security Guide](./SECURITY-GUIDE.md)
- [Performance Guide](./PERFORMANCE-GUIDE.md)

---

*For additional support or questions, contact the ZipPicks team at support@zippicks.com*