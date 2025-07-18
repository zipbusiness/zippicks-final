# ZipPicks Master Critic Schema.org Implementation

## Overview

This document describes the ItemList schema implementation for ZipPicks Master Critic lists, designed to make these pages rank at the top of Google search results through rich snippets and enhanced SERP features.

## Architecture

### Components

1. **Schema Generator** (`class-schema-generator.php`)
   - Converts business data to Schema.org ItemList format
   - Supports multiple business types (Restaurant, Hotel, etc.)
   - Validates schema against Google requirements
   - Handles missing data gracefully

2. **Schema Hooks** (`class-schema-hooks.php`)
   - Injects schema into page `<head>` via `wp_head` hook
   - Provides optional content injection
   - Implements caching for performance
   - Adds admin meta box for schema preview

3. **Admin Notices**
   - Displays schema validation status on edit screens
   - Provides quick links to Google testing tools
   - Alerts about missing business data

## How It Works

### Schema Generation Flow

1. **List Creation**
   - AI generates businesses with scores, reviews, and metadata
   - `ajax_create_list()` stores businesses as JSON in post meta
   - Business data includes all fields needed for schema

2. **Schema Output**
   - When a list page is viewed, schema is generated automatically
   - Schema is cached in post meta for 24 hours
   - Output in page head as JSON-LD script tag

3. **Validation**
   - Built-in validation ensures schema meets Google requirements
   - Admin notices alert about any issues
   - Testing tools help verify implementation

### Data Mapping

| Business Data | Schema Property | Notes |
|--------------|-----------------|-------|
| `name` | `item.name` | Business name |
| `score` | `aggregateRating.ratingValue` | Converted from 0-10 to 0-5 |
| `review_count` | `aggregateRating.reviewCount` | Number of reviews |
| `price_tier` | `priceRange` | $, $$, $$$, $$$$ |
| `summary` | `description` | Business description |
| `business_info.address` | `address.streetAddress` | Street address |
| `topic` | `servesCuisine` | For restaurants only |

## Implementation Details

### Supported Business Types

- `restaurant` → Schema.org/Restaurant
- `hotel` → Schema.org/Hotel  
- `salon` → Schema.org/BeautySalon
- `gym` → Schema.org/HealthClub
- `spa` → Schema.org/DaySpa
- `bar` → Schema.org/BarOrPub
- `cafe` → Schema.org/CafeOrCoffeeShop
- `custom` → Schema.org/LocalBusiness (default)

### Caching Strategy

1. **In-Memory Cache**: WordPress object cache (24 hours)
2. **Database Cache**: Post meta fields for persistence
3. **Cache Invalidation**: Cleared when post is updated

### Performance

- Average generation time: < 1ms per schema
- Minimal impact on page load
- Cached schemas served instantly

## Usage

### Creating Schema-Ready Lists

Lists created through the Master Critic AI generator are automatically schema-ready. The business data structure includes all necessary fields.

### Testing Schema

1. **Test Script**: `/wp-content/plugins/zippicks-master-critic/test-schema.php`
   - Validates schema generation
   - Tests existing lists
   - Checks performance

2. **Google Rich Results Test**
   - Available from admin meta box
   - Direct link: `https://search.google.com/test/rich-results?url=[YOUR_URL]`

3. **Schema Validator**
   - Built into admin interface
   - Shows errors and warnings

### Viewing Schema Output

1. View page source and search for "ZipPicks Master Critic ItemList Schema"
2. Check Schema Preview meta box in post editor
3. Use browser developer tools to inspect

## Maintenance

### Adding New Business Types

1. Add mapping in `BUSINESS_TYPE_MAPPING` constant
2. Update schema type detection logic
3. Add any type-specific properties

### Updating Schema Structure

1. Modify `generate_list_item()` method
2. Update validation rules if needed
3. Clear all schema caches after changes

### Troubleshooting

**Schema not appearing:**
- Check if businesses data exists in post meta
- Verify schema hooks are initialized
- Look for PHP errors in debug log

**Validation errors:**
- Check Schema Preview meta box for details
- Ensure all required fields are present
- Validate business data format

**Performance issues:**
- Check if caching is working
- Monitor schema generation time
- Consider increasing cache duration

## Best Practices

1. **Always validate** new lists with Google's tool
2. **Monitor** Search Console for rich result reports
3. **Keep data complete** - more data = better rankings
4. **Test changes** on staging before production
5. **Document** any customizations for future developers

## Google Guidelines

Following Google's requirements for ItemList:
- Each item must have a position
- Items must have consistent type
- List must have a name
- All items should have names

## Future Enhancements

1. **BreadcrumbList** schema for navigation
2. **Organization** schema for ZipPicks brand
3. **WebSite** schema with search action
4. **Review** schema for individual reviews
5. **FAQ** schema for list descriptions

## Resources

- [Google ItemList Documentation](https://developers.google.com/search/docs/appearance/structured-data/item-list)
- [Schema.org ItemList](https://schema.org/ItemList)
- [Rich Results Test](https://search.google.com/test/rich-results)
- [Schema Markup Validator](https://validator.schema.org/)

---

*Last updated: [Current Date]*
*Implementation by: ZipPicks Engineering Team*