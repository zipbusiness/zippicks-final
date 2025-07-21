# ZipPicks Architecture Decision Guide

## Quick Reference: Core vs Plugin

### 🏗️ Goes in CORE Plugin

**Data Structures & Persistence**
- ✅ Custom Post Types (CPTs)
- ✅ Taxonomies
- ✅ Meta field registrations
- ✅ Database tables for shared data
- ✅ User profile extensions

**Frontend & Display**
- ✅ Frontend templates (single, archive)
- ✅ REST API endpoints
- ✅ RSS feeds
- ✅ Sitemaps
- ✅ Schema.org markup

**Shared Functionality**
- ✅ Utilities used by multiple plugins
- ✅ Base classes and interfaces
- ✅ Common helper functions
- ✅ Caching layers
- ✅ Analytics tracking

### 🔧 Goes in FEATURE Plugins

**Business Logic**
- ✅ Content generation workflows
- ✅ Processing and automation
- ✅ Feature-specific algorithms
- ✅ Third-party API integrations
- ✅ Scheduled tasks for the feature

**Admin Interfaces**
- ✅ Feature management pages
- ✅ Settings pages
- ✅ Editorial tools
- ✅ Bulk operations
- ✅ Import/Export tools

**Feature-Specific Data**
- ✅ Temporary processing data
- ✅ Feature configuration
- ✅ API credentials
- ✅ Usage tracking for the feature
- ✅ Queue management

## Real Examples from ZipPicks

### Master Critic Plugin

**Goes in Core:**
```php
// Post type registration
register_post_type('master_critic_list', [...]);

// Meta fields
register_post_meta('master_critic_list', '_mc_topic', [...]);
register_post_meta('master_critic_list', '_mc_restaurants', [...]);

// Frontend template
single-master_critic_list.php

// REST endpoint
/wp-json/zippicks/v1/lists
```

**Stays in Master Critic:**
```php
// AI generation
class PromptBuilderService { }
class ClaudeClientService { }

// Editorial workflow
class PublishingService { }
class MasterCriticQueuePage { }

// Cost tracking
wp_zippicks_api_usage table
wp_zippicks_critic_results table
```

### Business Directory

**Goes in Core:**
```php
// CPT
register_post_type('zippicks_business', [...]);

// Taxonomy
register_taxonomy('zippicks_vibe', [...]);

// Meta fields
register_post_meta('zippicks_business', '_featured', [...]);
register_post_meta('zippicks_business', '_claimed', [...]);
```

**Stays in Business Plugin:**
```php
// Claiming workflow
class BusinessClaimService { }

// Verification process
class BusinessVerificationQueue { }

// Owner dashboard
class BusinessOwnerDashboard { }
```

## Decision Flow Chart

```
New Feature Request
    ↓
Is it a data structure?
    YES → Core Plugin
    NO ↓
    
Will data need to persist if plugin is off?
    YES → Data structure in Core, logic in plugin
    NO ↓
    
Will other plugins need to access this?
    YES → Core Plugin
    NO ↓
    
Is it a workflow or admin interface?
    YES → Feature Plugin
    NO ↓
    
Is it frontend display?
    YES → Core Plugin (or theme)
    NO → Feature Plugin
```

## Common Mistakes to Avoid

### ❌ DON'T: Put CPTs in feature plugins
```php
// WRONG - In Master Critic plugin
register_post_type('master_critic_list', [...]);
```

### ✅ DO: Put CPTs in Core
```php
// CORRECT - In Core plugin
register_post_type('master_critic_list', [...]);
```

### ❌ DON'T: Put feature workflows in Core
```php
// WRONG - In Core plugin
class AIGenerationService { }
```

### ✅ DO: Put workflows in feature plugins
```php
// CORRECT - In Master Critic plugin
class AIGenerationService { }
```

### ❌ DON'T: Create circular dependencies
```php
// WRONG - Core depending on Master Critic
if (class_exists('MasterCriticService')) {
    $service = new MasterCriticService();
}
```

### ✅ DO: Use Core's data structures
```php
// CORRECT - Master Critic using Core's CPT
wp_insert_post([
    'post_type' => 'master_critic_list', // Core's CPT
]);
```

## Testing Your Decision

Ask yourself:
1. If I deactivate the feature plugin, should the data still exist? 
   - YES = Data structure belongs in Core
   
2. If I'm building a new plugin, will it need this functionality?
   - YES = Shared functionality belongs in Core
   
3. Is this specific to how ONE feature works?
   - YES = Belongs in that feature plugin

## File Naming Conventions

### Core Plugin
```
zippicks-core/
├── includes/
│   ├── post-types/      # CPT registrations
│   ├── taxonomies/      # Taxonomy registrations  
│   ├── meta/            # Meta field definitions
│   ├── api/             # REST endpoints
│   └── schema/          # Schema.org markup
└── templates/           # Frontend templates
```

### Feature Plugin
```
zippicks-{feature}/
├── src/
│   ├── Admin/          # Admin pages
│   ├── Services/       # Business logic
│   ├── Workflows/      # Automation
│   └── Integrations/   # Third-party APIs
└── assets/             # Plugin-specific assets
```

## Golden Rules

1. **Core is the foundation** - It should work without any feature plugins
2. **Plugins extend Core** - They add features but don't own data structures
3. **Data lives forever** - User content must survive plugin deactivation
4. **No plugin dependencies** - Plugins can depend on Core, not each other
5. **Think reusability** - If two plugins need it, it goes in Core

## When in Doubt

If you're unsure where something belongs:
1. Start by putting it in the feature plugin
2. If another plugin needs it, refactor to Core
3. Always err on the side of keeping Core lean
4. Document your decision in code comments

Remember: It's easier to move something from a plugin to Core than vice versa.