# ZipPicks Database Structure Report

## Project Overview

ZipPicks is a comprehensive local discovery platform built on WordPress with a modular plugin architecture. The platform uses a foundation pattern with multiple feature plugins that handle different aspects of the business.

### Directory Structure

```
/Users/jeffsnewmacbook/Desktop/zippicks-final/
├── wp-content/
│   ├── mu-plugins/
│   │   ├── 00-zippicks-foundation.php (Simple MVP Foundation)
│   │   └── zippicks-foundation/
│   │       └── includes/
│   │           └── class-database-installer.php
│   └── plugins/
│       ├── zippicks-business/
│       ├── zippicks-core/
│       ├── zippicks-favorites/
│       ├── zippicks-master-critic/
│       └── zippicks-registration/
```

## Foundation Architecture

### ZipPicks Foundation (MU-Plugin)
- **Location**: `/wp-content/mu-plugins/00-zippicks-foundation.php`
- **Type**: Simple MVP version (can be upgraded to Enterprise later)
- **Key Features**:
  - Service Container (Dependency Injection)
  - Database Installer Service
  - Basic Cache Manager (array-based for MVP)
  - Simple Logger (PSR-3 compatible)
  - HTTP Client (WordPress HTTP API wrapper)
  - Storage Manager
  - Basic Auth system
  - Rate Limiter

### Database Installer Service
- **Class**: `ZipPicks\Foundation\Database_Installer`
- **Location**: `/wp-content/mu-plugins/zippicks-foundation/includes/class-database-installer.php`
- **Features**:
  - Register plugin schemas
  - Auto-install missing tables
  - Verify table existence
  - Support for progressive migration

## Database Schema Pattern

All ZipPicks plugins follow a robust database creation pattern with multiple fallback methods:

### Standard Pattern Components

1. **Database Class** (`includes/class-database.php`)
   - Table name constants
   - `get_[table]_table()` methods
   - `create_tables()` with dbDelta
   - `verify_tables()` for existence check
   - `get_schema_sql()` for Foundation integration
   - Alternative creation methods as fallback

2. **Installer Class** (`includes/class-installer.php`)
   - `install()` method for activation
   - `tables_exist()` static check
   - Foundation registration
   - Default data creation

3. **Main Plugin File**
   - Auto-create tables on init if missing
   - Admin notices for missing tables
   - Manual creation tools

4. **Manual Creation Tool** (`create-tables.php`)
   - Web interface for manual creation
   - Status display
   - Raw SQL output for phpMyAdmin

## Plugin Database Schemas

### 1. ZipPicks Business Plugin

**Purpose**: Centralized business management, monetization, verification, and analytics

**Tables**:

#### `wp_zippicks_business_analytics`
```sql
CREATE TABLE wp_zippicks_business_analytics (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    business_id bigint(20) NOT NULL,
    event_type varchar(50) NOT NULL,
    event_value varchar(255),
    user_id bigint(20),
    ip_address varchar(45),
    user_agent text,
    referrer text,
    session_id varchar(32),
    created_at datetime NOT NULL,
    PRIMARY KEY (id),
    KEY business_id (business_id),
    KEY event_type (event_type),
    KEY created_at (created_at),
    KEY user_id (user_id),
    KEY session_id (session_id)
)
```

#### `wp_zippicks_business_monetization`
```sql
CREATE TABLE wp_zippicks_business_monetization (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    business_id bigint(20) NOT NULL,
    tier varchar(20) NOT NULL DEFAULT 'basic',
    subscription_id varchar(100),
    subscription_status varchar(20),
    payment_method varchar(50),
    features text,
    amount decimal(10,2),
    currency varchar(3) DEFAULT 'USD',
    started_at datetime,
    expires_at datetime,
    last_payment_at datetime,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY business_id (business_id),
    KEY tier (tier),
    KEY expires_at (expires_at),
    KEY subscription_status (subscription_status)
)
```

#### `wp_zippicks_business_verification`
```sql
CREATE TABLE wp_zippicks_business_verification (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    business_id bigint(20) NOT NULL,
    verification_type varchar(50) NOT NULL,
    verification_status varchar(20) NOT NULL,
    verification_data text,
    verification_notes text,
    verified_by bigint(20),
    verified_at datetime,
    expires_at datetime,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY (id),
    KEY business_id (business_id),
    KEY verification_status (verification_status),
    KEY verification_type (verification_type),
    KEY expires_at (expires_at)
)
```

#### `wp_zippicks_scrape_log`
```sql
CREATE TABLE wp_zippicks_scrape_log (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    ip_address varchar(45) NOT NULL,
    request_path varchar(255) NOT NULL,
    user_agent text,
    referrer text,
    request_count int(11) DEFAULT 1,
    timestamp datetime NOT NULL,
    PRIMARY KEY (id),
    KEY ip_address (ip_address),
    KEY request_path (request_path),
    KEY timestamp (timestamp)
)
```

### 2. ZipPicks Favorites Plugin

**Purpose**: User favorites management with location-aware features

**Tables**:

#### `wp_zippicks_favorites`
```sql
CREATE TABLE wp_zippicks_favorites (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id bigint(20) UNSIGNED NOT NULL,
    business_id bigint(20) UNSIGNED NOT NULL,
    saved_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_notes text,
    latitude decimal(10, 8),
    longitude decimal(11, 8),
    city varchar(100),
    state varchar(50),
    country varchar(2) DEFAULT 'US',
    neighborhood varchar(100),
    zip_code varchar(10),
    PRIMARY KEY (id),
    UNIQUE KEY user_business (user_id, business_id),
    KEY user_id (user_id),
    KEY business_id (business_id),
    KEY location (latitude, longitude),
    KEY city_state (city, state),
    KEY saved_date (saved_date),
    KEY neighborhood (neighborhood),
    KEY zip_code (zip_code)
)
```

#### `wp_zippicks_favorites_meta`
```sql
CREATE TABLE wp_zippicks_favorites_meta (
    meta_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    favorite_id bigint(20) UNSIGNED NOT NULL,
    meta_key varchar(255) DEFAULT NULL,
    meta_value longtext,
    PRIMARY KEY (meta_id),
    KEY favorite_id (favorite_id),
    KEY meta_key (meta_key(191))
)
```

#### `wp_zippicks_location_cache`
```sql
CREATE TABLE wp_zippicks_location_cache (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    location_key varchar(255) NOT NULL,
    latitude decimal(10, 8) NOT NULL,
    longitude decimal(11, 8) NOT NULL,
    city varchar(100),
    state varchar(50),
    country varchar(2) DEFAULT 'US',
    neighborhood varchar(100),
    zip_code varchar(10),
    formatted_address text,
    cached_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY location_key (location_key),
    KEY cached_date (cached_date)
)
```

**Stored Procedures**:
- `zippicks_get_favorites_within_radius` - Geospatial radius search

### 3. ZipPicks Master Critic Plugin

**Purpose**: AI-powered content generation and list management

**Tables**:

#### `wp_zippicks_generations`
```sql
CREATE TABLE wp_zippicks_generations (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    business_category varchar(100) NOT NULL,
    topic varchar(255) NOT NULL,
    location varchar(255) NOT NULL,
    search_type varchar(50) NOT NULL,
    ai_provider varchar(50) NOT NULL,
    prompt longtext NOT NULL,
    ai_response longtext,
    businesses_created int(11) DEFAULT 0,
    list_id bigint(20) unsigned DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    status varchar(50) DEFAULT 'pending',
    PRIMARY KEY (id),
    KEY idx_category (business_category),
    KEY idx_location (location),
    KEY idx_status (status),
    KEY idx_created (created_at)
)
```

#### `wp_zippicks_prompt_templates`
```sql
CREATE TABLE wp_zippicks_prompt_templates (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    business_category varchar(100) NOT NULL,
    prompt_template longtext NOT NULL,
    is_default tinyint(1) DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_category (business_category),
    KEY idx_default (is_default)
)
```

#### `wp_zippicks_list_analytics`
```sql
CREATE TABLE wp_zippicks_list_analytics (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    post_id bigint(20) NOT NULL,
    event_type varchar(50) NOT NULL DEFAULT 'view',
    user_id bigint(20) DEFAULT NULL,
    ip_address varchar(45) DEFAULT NULL,
    user_agent text,
    referrer text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_post_id (post_id),
    KEY idx_user_id (user_id),
    KEY idx_created_at (created_at),
    KEY idx_event_type (event_type)
)
```

## Database Creation Methods

Each plugin implements multiple creation methods for reliability:

1. **Automatic Creation**
   - On plugin activation via `register_activation_hook`
   - On every init if tables are missing
   - Through Foundation's `ensure_tables_exist()`

2. **Manual Creation**
   - Admin notice with action button
   - Dedicated create-tables.php interface
   - Direct SQL execution fallback

3. **Foundation Integration**
   - Registers schema with Foundation installer
   - Participates in global table verification
   - Supports future migration system

## Security Features

1. **Anti-Scraping** (`wp_zippicks_scrape_log`)
   - Tracks suspicious access patterns
   - IP-based rate limiting
   - User agent analysis

2. **Session Management**
   - Secure cookie-based sessions
   - WordPress transient integration
   - SameSite cookie support

3. **Data Validation**
   - Prepared statements for all queries
   - Input sanitization
   - Output escaping

## Performance Optimizations

1. **Indexing Strategy**
   - Primary keys on all tables
   - Composite indexes for common queries
   - Foreign key indexes for JOINs

2. **Caching**
   - Location cache table for geocoding
   - Foundation cache service integration
   - Query result caching

3. **Geospatial Features**
   - Stored procedures for radius searches
   - Decimal precision for coordinates
   - Location-based indexes

## Enterprise Readiness

1. **Scalability**
   - Supports millions of records
   - Efficient indexing strategy
   - Partitioning-ready structure

2. **Maintainability**
   - Consistent naming conventions
   - Clear separation of concerns
   - Comprehensive error handling

3. **Extensibility**
   - Foundation service registration
   - Plugin-agnostic design
   - Future migration support

## Testing & Verification

Each plugin includes:
- `verify-tables.php` - Table existence check
- `create-tables.php` - Manual creation interface
- `test-activation.php` - Activation testing
- Admin notices for missing tables

## Recommendations

1. **Always verify tables exist** before operations
2. **Use Foundation installer** when available
3. **Implement graceful degradation** for missing services
4. **Monitor scrape logs** for security
5. **Regular table optimization** for performance