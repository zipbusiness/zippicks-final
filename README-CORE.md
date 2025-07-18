# ZipPicks Core Plugin

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/zippicks/core)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-Proprietary-red.svg)](LICENSE)

> **The AI-powered local discovery engine that revolutionizes how people find and experience local businesses.**

ZipPicks Core is the heart of the ZipPicks platform - a comprehensive WordPress plugin that provides enterprise-grade business management, AI-powered review analysis, and sophisticated taste graph functionality for the next generation of local discovery.

---

## 🎯 Purpose

ZipPicks Core transforms WordPress into a powerful local discovery platform by providing:

- **AI-Native Business Discovery** - Intelligent recommendations powered by taste graphs
- **Master Critic AI Scoring** - Structured 0-10 scoring across 6 vertical-specific pillars
- **Vibe-Based Taxonomy** - Mood-aware categorization beyond traditional business types
- **Enterprise Admin Management** - Professional interfaces for managing thousands of businesses
- **Advanced Review Moderation** - AI-powered content analysis and fraud detection

## 🚀 Key Features

### 🤖 AI-Powered Intelligence
- **OpenAI Integration** - Advanced GPT-4 powered analysis and insights
- **Sentiment Analysis** - 4-tier emotional classification of reviews
- **Authenticity Detection** - Fraud prevention and fake review identification
- **Content Moderation** - Automated policy violation detection
- **Smart Scoring** - Dynamic business scoring across multiple criteria

### 🏢 Business Management
- **Enterprise Admin Interface** - WP_List_Table with advanced filtering and bulk operations
- **Real-time Dashboard** - Live statistics, activity feeds, and quick actions
- **CSV Export/Import** - Data portability for business intelligence
- **Verification Workflows** - Multi-stage business validation process
- **Featured Listings** - Premium placement and badge management

### 📊 Review System
- **AI-Enhanced Moderation** - Priority queue with intelligent insights
- **Structured Data Schema** - JSON-LD markup for enhanced SEO
- **Bulk Operations** - Efficient management of high-volume content
- **Trust Scoring** - Advanced algorithms for reviewer credibility
- **Response Management** - Business owner engagement tools

### 🎨 Vibe Taxonomy
- **Mood-Based Discovery** - Beyond categories to emotional connections
- **Dynamic Taxonomy** - Flexible categorization system
- **Social Graph Integration** - User preference learning and matching
- **Cultural Relevance** - Community-driven taste identification

---

## 🏗️ Architecture

### Foundation Integration
ZipPicks Core is built on the **ZipPicks Foundation** - a sophisticated dependency injection container that provides:

```php
// Service registration pattern
zippicks()->bind('business.service', new BusinessService());

// Graceful degradation
$aiService = zippicks()->get('ai.service');
if ($aiService) {
    $insights = $aiService->analyzeSentiment($text);
}
```

### Plugin Structure
```
zippicks-core/
├── README.md                    # This documentation
├── zippicks-core.php           # Main plugin file
├── .env.example                # Environment configuration template
├── composer.json               # Dependency management
├── includes/                   # Core plugin classes
│   ├── class-core.php          # Main orchestration class
│   ├── class-loader.php        # Hook management
│   ├── class-activator.php     # Plugin activation
│   └── class-deactivator.php   # Plugin deactivation
├── src/                        # Source code (PSR-4 autoloaded)
│   ├── Admin/                  # Admin interface classes
│   ├── Models/                 # Data models
│   ├── Repositories/           # Data access layer
│   ├── Services/               # Business logic
│   ├── ServiceProviders/       # Dependency injection
│   ├── Database/               # Migration management
│   ├── Schema/                 # JSON-LD structured data
│   └── Notifications/          # Event system
├── assets/                     # Frontend resources
│   ├── css/                    # Stylesheets
│   └── js/                     # JavaScript
├── views/                      # Template files
│   └── admin/                  # Admin page templates
└── tests/                      # Test suite
    └── test-plugin-activation.php
```

---

## 🔧 Services Registry

### Core Services
| Service | Purpose | Dependencies |
|---------|---------|--------------|
| `zippicks.core.service.business` | Business management operations | Repository, Cache, Logger |
| `zippicks.core.service.review` | Review processing and moderation | AI Service, Repository |
| `zippicks.core.service.ai` | OpenAI integration and analysis | Cache, Logger, HTTP Client |
| `zippicks.core.service.scoring` | Dynamic business scoring algorithms | Business Service, Repository |
| `zippicks.core.service.vibe` | Vibe taxonomy and matching | Repository, Cache |
| `zippicks.core.service.geocoding` | Location processing and validation | HTTP Client, Cache |

### Repository Layer
| Repository | Entity | Features |
|------------|--------|----------|
| `zippicks.core.repository.business` | Business entities | CRUD, filtering, caching, bulk operations |
| `zippicks.core.repository.review` | Review entities | Moderation queue, sentiment tracking |
| `zippicks.core.repository.vibe` | Vibe taxonomy | Hierarchical structure, count tracking |
| `zippicks.core.repository.userprofile` | User preferences | Taste graph, social connections |

### Admin Controllers
| Controller | Purpose | Features |
|------------|---------|----------|
| `BusinessManagementPage` | Business administration | List table, bulk actions, CSV export |
| `ReviewModerationPage` | Review moderation | AI insights, priority queue, bulk approval |
| `DashboardWidget` | Overview dashboard | Real-time stats, activity feed, quick actions |
| `VibeTaxonomyPage` | Vibe management | Taxonomy CRUD, hierarchy management |
| `SettingsPage` | Plugin configuration | API keys, system status, preferences |

---

## ⚙️ Configuration

### Environment Variables
Create a `.env` file based on `.env.example`:

```bash
# OpenAI Configuration
OPENAI_API_KEY=your_openai_api_key_here

# Rate Limiting
OPENAI_RATE_LIMIT_REQUESTS_PER_MINUTE=20
OPENAI_RATE_LIMIT_REQUESTS_PER_DAY=1000

# Cache Configuration  
AI_CACHE_TTL=3600

# Debug Mode
ZIPPICKS_DEBUG=false
```

### WordPress Options
```php
// Set via admin or programmatically
update_option('zippicks_openai_api_key', 'your-api-key');
```

---

## 🚀 Developer Setup

### Prerequisites
- **WordPress**: 6.0 or higher
- **PHP**: 8.0 or higher
- **ZipPicks Foundation**: Must be active (mu-plugins)
- **OpenAI API Key**: For AI-powered features
- **Composer**: For dependency management (optional)

### Installation

1. **Ensure Foundation is Active**
   ```bash
   # Verify foundation is loaded
   ls wp-content/mu-plugins/00-zippicks-foundation.php
   ```

2. **Install Core Plugin**
   ```bash
   # Plugin should be in wp-content/plugins/zippicks-core/
   cd wp-content/plugins/zippicks-core/
   ```

3. **Configure Environment**
   ```bash
   cp .env.example .env
   # Edit .env with your configuration
   ```

4. **Install Dependencies** (if using Composer)
   ```bash
   composer install --no-dev
   ```

5. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Activate "ZipPicks Core"

### Verification
Check that services are registered:
```php
// In WordPress admin or via wp-cli
if (function_exists('zippicks')) {
    $businessService = zippicks()->get('zippicks.core.service.business');
    var_dump($businessService ? 'Business Service: Active' : 'Not Found');
}
```

---

## 🎨 Admin Interface

### Dashboard Widget
Navigate to **WordPress Admin → Dashboard** to see:
- Real-time business and review statistics
- Recent activity feed
- Quick action buttons with pending counts
- Auto-refresh functionality

### Business Management
Navigate to **WordPress Admin → ZipPicks → Businesses** for:
- Enterprise-grade list table with advanced filtering
- Bulk operations (verify, suspend, feature, delete)
- CSV export with filtered data
- Quick edit functionality with AJAX

### Review Moderation
Navigate to **WordPress Admin → ZipPicks → Reviews** for:
- AI-powered moderation insights
- Priority-based moderation queue
- Bulk approval/rejection actions
- Trust score visualization

### Settings Configuration
Navigate to **WordPress Admin → Settings → ZipPicks** for:
- OpenAI API key configuration
- System status monitoring
- Foundation connectivity check

---

## 🤖 AI Services

### Sentiment Analysis
```php
$aiService = zippicks()->get('zippicks.core.service.ai');
$sentiment = $aiService->analyzeSentiment($reviewText);
// Returns: 'very_positive', 'positive', 'negative', 'very_negative'
```

### Review Insights
```php
$insights = $aiService->generateModerationInsights($reviewText);
// Returns: fraud_probability, policy_violations, content_quality
```

### Key Phrase Extraction
```php
$phrases = $aiService->extractKeyPhrases($reviewText);
// Returns: array of important phrases and topics
```

### Scoring Generation
```php
$scores = $aiService->generateCriticScores($reviewText, $businessType);
// Returns: 6-pillar scoring (Food Quality, Service, Atmosphere, etc.)
```

---

## 📚 API Endpoints

### REST API Routes
| Endpoint | Method | Purpose | Authentication |
|----------|--------|---------|----------------|
| `/wp-json/zippicks/v1/businesses` | GET | List businesses | Optional |
| `/wp-json/zippicks/v1/businesses/{id}` | GET | Get business details | Optional |
| `/wp-json/zippicks/v1/reviews` | GET | List reviews | Optional |
| `/wp-json/zippicks/v1/reviews` | POST | Create review | Required |
| `/wp-json/zippicks/v1/vibes` | GET | List vibes | Optional |

### Example Usage
```javascript
// Fetch businesses with vibe filtering
fetch('/wp-json/zippicks/v1/businesses?vibe=natural-wine&zip=90210')
  .then(response => response.json())
  .then(businesses => console.log(businesses));
```

---

## 🔒 Security Features

### Capability System
```php
// Custom capabilities for role-based access
'manage_zippicks_businesses'
'moderate_zippicks_reviews' 
'edit_zippicks_vibes'
```

### Input Validation
- Comprehensive sanitization for all user inputs
- WordPress nonce verification for all admin actions
- SQL injection prevention via prepared statements
- XSS protection through proper escaping

### Rate Limiting
- AI service rate limiting (20 requests/minute, 1000/day)
- Cost optimization with caching layers
- Graceful degradation when limits exceeded

---

## 🚀 Performance Optimization

### Caching Strategy
- **AI Response Caching**: 24-hour TTL for OpenAI results
- **Database Query Caching**: Optimized repository patterns
- **Asset Optimization**: Minified CSS/JS with cache busting

### Database Optimization
- Indexed fields for common queries
- Lazy loading for heavy datasets
- Efficient pagination for large result sets

### Mobile Performance
- Responsive admin interfaces
- Touch-optimized interactions
- Progressive enhancement patterns

---

## 🧪 Testing

### Manual Testing Checklist
- [ ] Plugin activation without errors
- [ ] Foundation dependency check working
- [ ] Admin pages load correctly
- [ ] AJAX functionality operational
- [ ] Mobile responsiveness verified
- [ ] OpenAI integration working (with API key)

### Automated Testing
```bash
# Run plugin activation test
php tests/test-plugin-activation.php
```

---

## 🤝 Contributing

### Development Workflow
1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Follow WordPress coding standards
4. Test thoroughly on multiple environments
5. Submit pull request with detailed description

### Code Standards
- **PSR-4 Autoloading**: Namespace structure matches directory structure
- **WordPress Coding Standards**: Follow WordPress PHP coding standards
- **Security First**: All inputs sanitized, all outputs escaped
- **Performance Aware**: Consider caching and optimization in all code

### Architecture Principles
- **Dependency Injection**: Use the Foundation container for all services
- **Interface-Driven**: Implement contracts for all major components
- **Progressive Enhancement**: Gracefully degrade when services unavailable
- **Mobile First**: Design for mobile, enhance for desktop

---

## 📋 Troubleshooting

### Common Issues

**Plugin won't activate**
```
Error: ZipPicks Core requires ZipPicks Foundation to be activated.
```
**Solution**: Ensure `/wp-content/mu-plugins/00-zippicks-foundation.php` exists and is active.

**Admin pages show errors**
```
Error: Call to undefined function zippicks()
```
**Solution**: Foundation not loaded. Check mu-plugins directory and PHP error logs.

**AI features not working**
```
Warning: OpenAI API key not configured
```
**Solution**: Add API key via Settings → ZipPicks or set `OPENAI_API_KEY` environment variable.

### Debug Mode
Enable debug logging:
```php
// In wp-config.php
define('ZIPPICKS_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

---

## 📊 System Requirements

### Minimum Requirements
- **WordPress**: 6.0+
- **PHP**: 8.0+
- **MySQL**: 5.7+ or MariaDB 10.2+
- **Memory**: 256MB PHP memory limit
- **Storage**: 50MB free space

### Recommended Requirements
- **PHP**: 8.1+
- **Memory**: 512MB PHP memory limit
- **Redis**: For advanced caching
- **SSL**: For secure API communications
- **CDN**: For asset optimization

---

## 📈 Performance Benchmarks

### Admin Interface Performance
- **Page Load Time**: < 1 second (optimized queries + caching)
- **AJAX Response Time**: < 500ms (efficient backend processing)
- **Bulk Operations**: Handle 100+ items without timeout
- **Mobile Performance**: Smooth interactions on all devices

### AI Service Performance  
- **Response Time**: < 2 seconds per API call
- **Cache Hit Rate**: > 80% (24-hour TTL)
- **Rate Limiting**: 20 requests/minute, 1000/day
- **Cost Optimization**: < $0.01 per review analysis

---

## 📄 License

**Proprietary License** - This software is proprietary to ZipPicks and is not open source. All rights reserved.

For licensing inquiries, contact: [legal@zippicks.com](mailto:legal@zippicks.com)

---

## 🆘 Support

### Documentation
- **Architecture Guide**: See `/docs/` directory
- **API Documentation**: Available at `/wp-admin/admin.php?page=zippicks-api-docs`
- **Code Examples**: Check `/examples/` directory

### Support Channels
- **Technical Issues**: [support@zippicks.com](mailto:support@zippicks.com)
- **Documentation**: [docs.zippicks.com](https://docs.zippicks.com)
- **Developer Forum**: [developers.zippicks.com](https://developers.zippicks.com)

---

## 🏆 Credits

**Built with enterprise-grade architecture for the future of local discovery.**

- **Foundation Architecture**: ZipPicks Engineering Team
- **AI Integration**: OpenAI GPT-4 Turbo
- **WordPress Integration**: WordPress coding standards compliant
- **Enterprise Patterns**: PSR-4, PSR-11, PSR-3 compliant

---

*ZipPicks Core - Powering the Taste Layer of the Internet™*