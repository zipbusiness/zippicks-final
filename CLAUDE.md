# CLAUDE.md – ZipPicks Master Session

## 🏆 ENTERPRISE ENGINEERING STANDARDS

**Every line of code written for ZipPicks must meet the standards of a Fortune 500 enterprise architect.**

### Core Engineering Principles

1. **NO THEATRICAL CODE** - If it doesn't genuinely work, it doesn't ship
2. **NO CARGO CULT PROGRAMMING** - Understand every line; remove what isn't needed
3. **NO BAND-AID SOLUTIONS** - Fix root causes, not symptoms
4. **NO WISHFUL THINKING** - Code either works or it doesn't; there's no "mostly works"
5. **NO RESUME-DRIVEN DEVELOPMENT** - Use the right tool, not the trendy one

### Enterprise Code Requirements

**Every implementation MUST:**
- **Actually work** - Not appear to work, not work sometimes, but reliably function
- **Handle edge cases** - Null values, empty sets, malformed input, concurrent access
- **Scale properly** - Work with 10 records or 10 million records
- **Fail gracefully** - Clear error messages, rollback capabilities, recovery paths
- **Be maintainable** - Another engineer should understand it in 6 months
- **Have genuine purpose** - If you can't explain why it exists, delete it

### Examples of Theatrical vs Enterprise Code

❌ **THEATRICAL** (Appears to work but doesn't):
```php
// "Security" that any browser can bypass
if (!empty($_SERVER['HTTP_USER_AGENT'])) {
    // "Protected" content
}

// Cache that doesn't actually cache
$cache = get_transient('data');
if (!$cache) {
    $cache = expensive_operation();
    // Missing: set_transient()
}

// Error handling that hides problems
try {
    risky_operation();
} catch (Exception $e) {
    // Silent failure = future debugging nightmare
}
```

✅ **ENTERPRISE** (Actually works):
```php
// Real authentication
if (!current_user_can('required_capability')) {
    wp_die('Unauthorized', 403);
}

// Cache that actually caches
$cache = get_transient('data');
if ($cache === false) {
    $cache = expensive_operation();
    set_transient('data', $cache, HOUR_IN_SECONDS);
}

// Error handling that helps
try {
    risky_operation();
} catch (Exception $e) {
    error_log('Operation failed: ' . $e->getMessage());
    throw new UserFacingException('Unable to complete request. Please try again.');
}
```

### The "Would This Pass Code Review?" Test

Before writing ANY code, ask yourself:
- Would a senior engineer at Google approve this?
- Would this survive a security audit?
- Would this work in production under load?
- Would I be proud to present this at a technical conference?
- Would I want to maintain this code in 2 years?

If any answer is "no", rewrite it.

## ⚠️ CRITICAL LEGAL NOTICE ⚠️

**Any false promises or work not actually complete will result in legal action.**

### Verification Requirements
- All claimed features MUST be demonstrable in real-time
- "Complete" means fully functional, not partially implemented
- Documentation must match actual code implementation
- Progress reports must include working demonstrations

### Definition of Fraud
The following constitutes fraudulent misrepresentation:
- Claiming features exist when code is missing
- Marking work "complete" when it doesn't function
- Creating documentation for non-existent features
- Misrepresenting progress percentages

### Required Proof of Work
1. **Live demonstration** of every claimed feature
2. **Code verification** - files must exist and function
3. **Integration testing** - features must work together
4. **Error-free operation** - no fatal errors or breaks

**Previous fraud example**: Core plugin claimed "99% complete" but was ~40% done, with entire AI system non-existent despite being documented as complete.

## DATA PROTECTION & ANTI-SCRAPING POLICY (MANDATORY)

**NO SECURITY THEATER**: All security implementations MUST be effective, not performative. If a protection mechanism can be trivially bypassed, it must not be implemented. Instead, implement genuine security measures or explicitly document why certain data must remain public.

ZipPicks content must be engineered to prevent scraping, cloning, unauthorized redistribution, or commoditization. All current and future feature plugins — including Top 10 lists, Vibe archives, Smart Search results, Critic profiles, and Business listings — must strictly follow the protections below. Violation of these rules constitutes a security breach and invalidates the build.

1. Rendering Controls
	•	Core content (Top 10 lists, Vibe results, etc.) must be rendered client-side using AJAX/REST + JavaScript hydration.
	•	No static output of business names, list items, or scores in raw HTML or page source.
	•	Structured data (e.g., JSON-LD schema) must be generated dynamically at render time and injected by JS.
	•	All list containers must use fragment rendering patterns (<div class="v-card">) triggered only after frontend hydration.

2. REST & API Endpoint Security
	•	REST and AJAX endpoints must:
	•	Require session-bound nonces or tokens
	•	Log all requests with IP, endpoint, and headers
	•	Rate-limit based on user type (public, critic, business, admin)
	•	Reject empty User-Agent headers or CLI agents (e.g. curl)
	•	All endpoints must include:
	•	X-Robots-Tag: noindex
	•	Cache-Control: private, max-age=0
	•	X-ZipPicks-Source: frontend-only
	•	Claude must implement per-IP metering, detecting and throttling abnormal access patterns.

3. Client-Side Rendering Policy
	•	All content output must avoid <li>, <ol>, <table> HTML patterns in static markup.
	•	Business cards, critic tiles, and list entries must be rendered using Vue/React-style hydration or JSON-fetch patterns.
	•	Use delayed content load with progressive UI (Loading… skeletons) to discourage screen scraping bots.
	•	Where fallback content is required (e.g. SEO fallback), Claude must serve partials or teasers only — not full data.

4. Content Obfuscation
	•	Class names must use obscure or hashed identifiers (.zp-card-a8d3f9, .zp-rank-block-X3) rather than semantic tags like .restaurant-card.
	•	Claude must avoid embedding pillar scores, AI summaries, or metadata in visible markup or <script> tags.
	•	Internal variables (e.g. taste vector IDs) must only exist in client memory during runtime.
	•	Use short-lived cache keys (e.g. 5-minute TTL) for any public content to simulate real-time variability.

5. Watermarking & Fingerprinting
	•	All Claude-generated content must contain invisible watermarks:
	•	HTML: <span class="zp-fp" data-hash="ZP42397fa8d">
	•	Schema: zippicks_fingerprint field in JSON-LD output
	•	Image overlays: Transparent 1px SVG data URI
	•	Watermarks must be unique per session or user and allow DMCA traceback.
	•	Copying, saving, or reindexing pages without removing these traps will reveal scraping activity.

6. Invisible Copy Traps
	•	Claude must inject hidden copy traps:
	•	Rendered only on frontend
	•	Invisible spans with unique noise content (display:none)
	•	Tracked via impression logs
	•	Traps must be placed in list order, summary text, and critic attributions.

7. View Expiry & Session Gating
	•	All list or ranking views must:
	•	Auto-expire cache after short periods (5–15 mins)
	•	Require valid session token or cookie to load full content
	•	Allow fallback previews but no full results for unauthenticated bots

8. Robots.txt & Sitemap Lockdown
	•	Claude must maintain a protected robots.txt:
User-agent: *
Disallow: /api/
Disallow: /zippicks_list/
Disallow: /vibes-api/
Disallow: /smart-search/

	•	Sitemap.xml files:
	•	Must exclude programmatic list archives
	•	Only expose pages explicitly marked public=1
	•	Claude must not auto-include new Top 10s or Vibes without founder approval

9. Scrape Watchdog Logging
	•	A dedicated zippicks_scrape_log database table must track:
	•	IP address
	•	Request path
	•	User agent
	•	Referrer
	•	Timestamp
	•	Watchdog must trigger alerts on:
	•	More than 10 requests/minute to list endpoints
	•	Requests from known scraping IPs
	•	Sequential access to multiple list/detail pages

10. Auth-Only Data Disclosure
	•	Full critic summaries, simulated reviews, AI vectors, and ranking scores must:
	•	Only display to logged-in users
	•	Require active session with rotating nonce
	•	Be part of interactive UX (Smart Search, profile pages)
	•	Public-facing summaries should show shortened or editorialized versions

11. Content Rotation & Freshness
	•	Lists should appear dynamic:
	•	Claude must support randomized order or timestamp-based reshuffling
	•	Top 10s must display “updated X days ago” logic
	•	Content must degrade subtly over time if not refreshed


### Effective Security Requirements

**All implementations MUST pass the "bypass test"**: If a moderately skilled developer can bypass the protection in under 5 minutes, the implementation is theater and must be replaced with:

1. **Server-side authentication** - Require user login for valuable data
2. **Legal protection** - Terms of Service, DMCA enforcement
3. **CloudFlare/WAF protection** - Professional anti-bot services
4. **API key requirements** - Tracked and rate-limited access
5. **Honeypot data** - Detectable fake entries to identify scrapers

**Explicitly allowed public data**: If business requirements mandate public access to certain data (for SEO, user acquisition, etc.), document this clearly and do not implement fake protections. Instead, focus on:
- Legal terms prohibiting scraping
- Monitoring and detection of abuse
- Business model that doesn't depend on data exclusivity

Failure to follow this policy will be considered a security breach.

---

# Behavior
- When a plan is completed, YOU MUST execute the file writes to disk using shell or filesystem access.
- Do NOT simulate file creation with cat >> syntax. Actually write files using Claude Code tools.

## Project Setup

- Root directory: `/Users/jeff/Desktop/zippicks-final/`
- Claude should ALWAYS read:
  - `business-plan.txt`
  - `platform-architecture.txt`
  - `README-CORE.md` (Core plugin architecture & services)
- Directory structure includes:
  - `mu-plugins/zippicks-foundation`
  - `plugins/zippicks-core`
  - `themes/zippicks-child` (GeneratePress child theme)

## Build Philosophy

- This is a $100M+ platform build — code like it
- Every function must withstand enterprise-level scrutiny
- Follow modular architecture and WordPress best practices
- All logic must be reusable, secure, and cache-aware
- Do not patch, guess, or assume file contents — verify them
- Follow Claude Code atomic sprint workflow: 1 clean win at a time
- **Write code you'd be proud to show Linus Torvalds**

## 🚨 CRITICAL: Core vs Plugin Architecture

**BEFORE writing ANY code, you MUST determine where it belongs:**

### What Goes in CORE Plugin (`zippicks-core`)
Core owns **data structures** and **shared functionality**:
- Custom Post Type registrations (businesses, reviews, lists, etc.)
- Taxonomies (vibes, categories, locations)
- Meta field definitions
- Frontend templates
- REST API endpoints
- Schema.org/SEO implementation
- Shared utilities used by multiple plugins
- Analytics tracking (data persists even if feature plugins are deactivated)

### What Goes in Feature Plugins
Feature plugins own **business logic** and **workflows**:
- Content generation workflows
- Admin interfaces for their specific features
- Feature-specific settings
- Processing and automation
- Editorial tools
- Feature-specific API integrations

### Examples:
- **Master Critic**: Owns AI generation, editorial workflow, cost tracking
  - BUT uses Core's `master_critic_list` CPT to store the data
- **Monetization**: Owns payment processing, subscription logic
  - BUT uses Core's meta fields on `zippicks_business` CPT
- **Users**: Owns social features, following system
  - BUT uses Core's user profile extensions

### Key Principle:
> **Core owns the "what" (data structures), Plugins own the "how" (features)**

This ensures:
- Data persists if plugins are deactivated
- No circular dependencies
- Clean, maintainable architecture
- Other plugins can interact with core data structures

## Thinking Instructions

- You MUST **think before you code**
- Use `ultrathink` when prompted — or default to it by design
- Begin every sprint with a plan. Break work into isolated steps
- **FIRST QUESTION**: "Would a Fortune 500 architect approve this approach?"
- **SECOND QUESTION**: "Should this code go in Core or a feature plugin?"
- If the build scope is large, ask: "Should I split this into phases?"
- Always verify that required files exist before referencing them
- Prefer subagent thinking for file validation or multi-part changes
- **Before implementation**: "Is this the solution or just theater?"

## Architecture Decision Checklist

When implementing ANY new feature, ask yourself:

1. **Is this a data structure?** → Goes in Core
   - Post types, taxonomies, meta fields
   - Database tables for shared data
   - API endpoints for accessing data

2. **Is this a workflow/feature?** → Goes in feature plugin
   - Admin pages for managing the feature
   - Processing logic
   - Feature-specific settings

3. **Will other plugins need this?** → Goes in Core
   - Shared utilities
   - Common interfaces
   - Base classes

4. **Should data persist if plugin is deactivated?** → Data structure goes in Core
   - User-generated content
   - Business data
   - Analytics data

5. **Is this feature-specific UI?** → Goes in feature plugin
   - Admin interfaces
   - Settings pages
   - Editorial tools

## Testing + Validation

### Enterprise Testing Standards

**The "It Works On My Machine" Excuse Dies Here**

- **Load Testing**: Will this work with 1M+ records?
- **Concurrency Testing**: What happens with 100 simultaneous users?
- **Edge Case Testing**: Empty sets, nulls, malformed data, Byzantine failures
- **Security Testing**: SQL injection, XSS, CSRF, privilege escalation
- **Performance Testing**: Sub-second response times under load

### Required Validation

- You MUST verify that:
  - All CPTs load properly in the WordPress admin
  - The child theme activates without fatal errors
  - REST API endpoints work (use Postman or CLI)
  - Schema and shortcodes produce valid markup
  - **No silent failures** - errors are logged and handled
  - **No infinite loops** - all iterations have bounds
  - **No memory leaks** - resources are properly released
  - **No race conditions** - concurrent access is safe
- Do not push code without confirming all paths are correct

## Coding Conventions

### Enterprise Code Standards

- Prefix all custom PHP classes with `ZipPicks_`
- Use namespaced functions inside plugins, not global
- Avoid WP hooks that depend on execution timing unless documented
- Place all assets in `/assets/` and never inline styles/scripts
- Use semantic markup and class-based styling in templates

### The Enterprise Difference

❌ **Amateur Hour**:
- Magic numbers everywhere
- Copy-paste programming
- "It works sometimes"
- TODO comments from 2019
- Functions doing 17 different things

✅ **Enterprise Grade**:
- Named constants with clear purpose
- DRY principle properly applied
- Deterministic behavior
- Technical debt tracked and addressed
- Single Responsibility Principle

### Code Smells That Will Get Rejected

- `@suppress` without explanation
- `sleep()` as a "fix" for race conditions
- Commented-out code "just in case"
- Empty catch blocks
- Functions over 50 lines
- Classes doing unrelated things
- Copy-pasted Stack Overflow without understanding

## 🚨 CRITICAL: PHP 8.3 Strict Typing Requirements

**ZipPicks runs on PHP 8.3.x which enforces strict return types. ALL methods MUST return exactly what they declare.**

### Return Type Validation Rules

1. **Methods declaring `array` return type**:
   ```php
   public function getItems(): array {
       $results = $wpdb->get_results($query);
       
       // ❌ FATAL ERROR - can return null
       return $results;
       
       // ✅ ALWAYS SAFE
       return is_array($results) ? $results : [];
   }
   ```

2. **Cache validation pattern**:
   ```php
   if ($cached !== false) {
       // ❌ FATAL ERROR - cached value might not be array
       return $cached;
       
       // ✅ ALWAYS SAFE  
       return is_array($cached) ? $cached : [];
   }
   ```

3. **Database result handling**:
   ```php
   $data = $wpdb->get_results($query);
   
   // ❌ DANGEROUS - get_results() returns null on error
   return $data;
   
   // ✅ SAFE PATTERN
   $result = is_array($data) ? $data : [];
   if ($data === null && $this->logger) {
       $this->logger->warning('Database query failed', ['error' => $wpdb->last_error]);
   }
   return $result;
   ```

### Common Fatal Error Patterns

- `TypeError: Return value must be of type array, null returned`
- `TypeError: Return value must be of type int, null returned`  
- `TypeError: Return value must be of type string, null returned`

### Required Fixes for ALL Plugins

**Every method with a return type declaration MUST:**
1. **Validate cached results** before returning
2. **Handle database null responses** with fallbacks
3. **Use type-safe patterns** consistently
4. **Log warnings** when fallbacks are triggered

### Testing Requirement

- Test ALL admin pages and endpoints in PHP 8.3 environment
- Check error logs for TypeError fatals
- Verify return type compliance across all methods

## Claude Code Workflow

- Start each sprint with: `/clear`, `/read`, `/plan`, `/code`
- Use `/read` on related files before writing any logic
- Use `/plan` to outline atomic changes
- Use `/code` or `/project:<task>` to implement
- If asked to build too much, say: "This task should be broken into atomic steps."

## Naming

- Child theme folder: `zippicks-child`
- MU Plugin: `zippicks-foundation`
- Core plugin: `zippicks-core`
- Business CPT: `zippicks_business`
- Review CPT: `zippicks_review`

## Foundation Architecture

- Currently using **Simple Foundation** for MVP launch
- Enterprise Foundation available at `00-zippicks-foundation-enterprise.php.backup`
- Service registration pattern works with BOTH foundations:

### Service Registration Pattern

```php
// Register a service (same code for both foundations)
zippicks()->bind('vibes', new VibeManager());

// Use services with graceful degradation
$cache = zippicks()->get('cache');
if ($cache) {
    $cached = $cache->get('my_data');
}
```

### Progressive Enhancement Rules

1. **Always check if service exists** before using it
2. **Never require** enterprise services in MVP code
3. **Write plugins** to enhance when services are available
4. **Same plugin code** works with both foundations

### Example Pattern:

```php
class MyService {
    private $cache = null;
    private $logger = null;
    
    public function __construct() {
        // Gracefully use services if available
        if (zippicks()->has('cache')) {
            $this->cache = zippicks()->get('cache');
        }
        if (zippicks()->has('logger')) {
            $this->logger = zippicks()->get('logger');
        }
    }
}
```

## Foundation Logging Checklist

- [ ] Log directory exists or is created
- [ ] PSR-3 logger works across all levels
- [ ] Logging context is interpolated and preserved
- [ ] Logger alias is available in all environments

## Database Migration & Evolution System

All ZipPicks plugins MUST implement the database migration system to ensure safe schema evolution as new features are added.

### 🚨 CRITICAL: Migration System Requirements

**EVERY new plugin with database tables MUST include:**

1. **Database Migrator Class** (`includes/class-database-migrator.php`)
2. **Versioned Schema Evolution** - Never break existing tables
3. **Foundation Registration** - Prevent cross-plugin conflicts
4. **Admin Migration Interface** - User-friendly migration buttons
5. **Rollback Capabilities** - Emergency recovery options

### Required Components for Each Plugin

1. **Database Migrator Class** (`includes/class-database-migrator.php`)
   ```php
   class ZipPicks_[Plugin]_Database_Migrator {
       const CURRENT_SCHEMA_VERSION = '1.2.0';
       const VERSION_OPTION = 'zippicks_[plugin]_db_version';
       
       private static $migrations = [
           '1.0.0' => 'migrate_to_1_0_0',
           '1.1.0' => 'migrate_to_1_1_0',
           '1.2.0' => 'migrate_to_1_2_0',
       ];
   }
   ```

2. **Database Class** (`includes/class-database.php`)
   - Define table names as class constants with proper prefixes
   - Implement `get_[table]_table()` methods for each table
   - Create `create_tables()` method using `dbDelta()`
   - Include `verify_tables()` method to check existence
   - Provide `get_schema_sql()` for Foundation integration
   - **NEW**: Add individual table SQL methods for Foundation registration

3. **Enhanced Installer Class** (`includes/class-installer.php`)
   - **PRIORITY**: Use migration system first, fallback to direct creation
   - Implement `install()` method that runs migrations
   - Add `tables_exist()` static method for checking
   - Handle version tracking with options (plugin AND database versions)
   - Set default plugin options
   - Create necessary roles/capabilities
   - Register with Foundation database installer

4. **Main Plugin File** - Migration-Aware Admin Notices
   - Check migration status and display appropriate notices
   - Handle migration actions with proper security
   - Provide both migration and fallback creation options

5. **Manual Creation Tool** (`create-tables.php`)
   - Web-based interface for manual table creation
   - Show current migration status
   - Provide multiple creation methods
   - Include raw SQL for phpMyAdmin option

### Table Naming Convention (MANDATORY)

**Pattern**: `{wp_prefix}zippicks_{feature}_{table}`

✅ **CORRECT**:
- `wp_zippicks_business_listings`
- `wp_zippicks_critic_reviews`
- `wp_zippicks_vibes_taxonomy`
- `wp_zippicks_master_generations`

❌ **FORBIDDEN** (Conflict Risk):
- `wp_businesses`
- `wp_reviews`
- `wp_data`
- `wp_lists`

### Migration Implementation Example

```php
// In installer class
public static function install() {
    // Load migration system
    require_once PLUGIN_DIR . 'includes/class-database-migrator.php';
    
    // Run database migrations
    $migration_result = ZipPicks_Plugin_Database_Migrator::run_migrations();
    
    // If migrations failed, try fallback table creation
    if ($migration_result['status'] !== 'success' && $migration_result['status'] !== 'up_to_date') {
        require_once PLUGIN_DIR . 'includes/class-database.php';
        ZipPicks_Plugin_Database::create_tables();
    }
    
    // Register with Foundation
    self::register_with_foundation();
}

// Foundation registration
private static function register_with_foundation() {
    if (function_exists('zippicks') && zippicks()->has('database.installer')) {
        $installer = zippicks()->get('database.installer');
        $installer->register_schema('plugin-name', function() {
            return [
                'table1' => ZipPicks_Plugin_Database::get_table1_sql(),
                'table2' => ZipPicks_Plugin_Database::get_table2_sql(),
            ];
        }, ZipPicks_Plugin_Database_Migrator::CURRENT_SCHEMA_VERSION);
    }
}
```

### Admin Action Handlers

```php
// In admin class display_page() method
if (isset($_GET['action']) && $_GET['action'] === 'run-migration') {
    // Security checks
    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions'));
    }
    
    if (!wp_verify_nonce($_GET['_wpnonce'], 'run_migration_action')) {
        wp_die(__('Security check failed'));
    }
    
    // Run migration
    $result = ZipPicks_Plugin_Database_Migrator::run_migrations();
    $status = $result['status'] === 'success' ? 'migration-success' : 'migration-failed';
    
    wp_redirect(admin_url("admin.php?page=plugin&migration-result={$status}"));
    exit;
}
```

### Admin Notices Pattern

```php
// Check migration status first
$migration_status = ZipPicks_Plugin_Database_Migrator::get_migration_status();

if ($migration_status['needs_migration']) {
    $migrate_url = wp_nonce_url(
        admin_url('admin.php?page=plugin&action=run-migration'),
        'run_migration_action'
    );
    ?>
    <div class="notice notice-warning">
        <p>
            <strong>Plugin Name:</strong> Database needs migration to version <?php echo esc_html($migration_status['target_version']); ?>.
            <a href="<?php echo esc_url($migrate_url); ?>" class="button button-primary">Run Migration</a>
        </p>
    </div>
    <?php
} elseif (!Plugin_Installer::tables_exist()) {
    // Fallback for missing tables
}
```

### Migration Versioning Strategy

**Version Format**: `MAJOR.MINOR.PATCH`

- **MAJOR** (2.0.0): Breaking changes, data structure overhauls
- **MINOR** (1.1.0): New tables, new features, schema additions
- **PATCH** (1.0.1): Index additions, small optimizations

**Migration Progression**:
```php
'1.0.0' => 'migrate_to_1_0_0', // Initial tables
'1.1.0' => 'migrate_to_1_1_0', // Add feature tables
'1.2.0' => 'migrate_to_1_2_0', // Add monitoring tables
'1.3.0' => 'migrate_to_1_3_0', // Add new feature (future)
```

### Key Principles

1. **Migration First**: Always use migration system, fallback to direct creation
2. **Version Separation**: Plugin version ≠ Database schema version
3. **Conflict Prevention**: Strict naming conventions prevent table conflicts
4. **Foundation Integration**: Register all schemas centrally
5. **User Experience**: Clear notices with one-click migration buttons
6. **Safety First**: Lock mechanisms prevent concurrent migrations
7. **Rollback Ready**: Every migration must be reversible
8. **Testing Required**: Test migration paths thoroughly

### Foundation Integration Requirements

```php
// REQUIRED: Every plugin must register with Foundation
$installer->register_schema('unique-plugin-name', $callback, $version);

// Foundation provides centralized:
// - Conflict detection across plugins
// - Unified status monitoring  
// - Cross-plugin dependency management
```

### Testing Requirements

- [ ] Fresh install (0.0.0 → latest version)
- [ ] Incremental migrations (1.0.0 → 1.1.0 → 1.2.0)
- [ ] Migration rollback scenarios
- [ ] Concurrent migration prevention
- [ ] Foundation registration verification
- [ ] Admin notice functionality
- [ ] Manual creation fallbacks

### Emergency Procedures

**If Migration Fails**:
1. Check migration lock: `delete_transient('plugin_migration_lock')`
2. Reset database version: `delete_option('zippicks_plugin_db_version')`
3. Use manual creation tool: `/plugin/create-tables.php`
4. Check Foundation status page

**Complete Reset** (Development Only):
```sql
-- Reset all plugin tables and versions
DROP TABLE IF EXISTS wp_zippicks_plugin_*;
DELETE FROM wp_options WHERE option_name LIKE 'zippicks%db_version%';
```

## Reminders

- **Code like your reputation depends on it** - because it does
- Think before coding - theatrical solutions waste everyone's time
- Don't start without reading the project files
- Never move forward if any file is missing or unclear
- Respect the locked architecture already implemented
- ALWAYS implement the full database creation pattern for plugins with tables
- **If it doesn't actually work, don't pretend it does**
- **If you wouldn't deploy it at Netflix, don't deploy it here**