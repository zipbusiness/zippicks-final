# ZipPicks Master Critic Plugin

AI-powered business ranking generator using Claude or GPT-4 for ANY business category. The first plugin in the ZipPicks modular system for building a comprehensive local discovery platform.

## Features

- **Dual AI Support**: Choose between Claude (Anthropic) or GPT-4 (OpenAI)
- **Universal Categories**: Works with restaurants, hotels, bars, spas, gyms, salons, and more
- **Dynamic Pillars**: Each category has 6 specific scoring pillars
- **Vibe Integration**: Generates mood-based tags for each business
- **Enterprise Admin**: Professional interface with bulk operations
- **Template System**: Save and reuse successful prompts
- **Rate Limiting**: Built-in API usage controls
- **Caching**: Smart caching to reduce API costs

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. Navigate to **Master Critic → Settings**
4. Add your AI API keys (Claude and/or GPT-4)
5. Start generating business rankings!

## Configuration

### API Keys

Get your API keys from:
- **Anthropic (Claude)**: https://console.anthropic.com/account/keys
- **OpenAI (GPT-4)**: https://platform.openai.com/api-keys

### Settings

- **Default AI Provider**: Choose your preferred AI service
- **Rate Limiting**: Set requests per minute/day limits
- **Cache Duration**: Configure how long to cache AI responses

## Usage

1. Go to **Master Critic** in the admin menu
2. Select a business category (Restaurant, Hotel, Bar, etc.)
3. Enter your topic (e.g., "tacos", "luxury hotels", "craft cocktails")
4. Specify location (city, state, or ZIP code)
5. Choose search type and AI provider
6. Generate and customize the prompt if needed
7. Execute AI generation
8. Create business pages from the results

## Business Categories

Each category includes specialized scoring pillars:

### Restaurant
- Food Quality
- Service
- Atmosphere & Design
- Value
- Consistency
- Cultural Relevance

### Hotel
- Room Quality
- Service
- Location
- Amenities
- Value
- Cleanliness

### Bar
- Drink Quality
- Atmosphere
- Service
- Music & Vibe
- Value
- Crowd

...and more categories available!

## Database Tables

The plugin creates two tables:
- `wp_zippicks_generations` - Stores AI generation history
- `wp_zippicks_prompt_templates` - Stores reusable prompts

If tables don't create automatically, use the manual creation tool:
`/wp-content/plugins/zippicks-master-critic/create-tables.php`

## Integration

The plugin fires WordPress hooks for integration:
```php
// When businesses are ranked
do_action('zippicks_business_ranked', $business_id, $context);

// When businesses are created
do_action('zippicks_business_created', $business_ids, $context);
```

## Foundation Support

Works with or without ZipPicks Foundation:
- **With Foundation**: Full integration with service container
- **Without Foundation**: Standalone mode with fallback post types

## Troubleshooting

### Tables not created
- Visit Settings page and click "Create Tables"
- Use the manual creation tool
- Check phpMyAdmin and run SQL manually

### API errors
- Verify API keys are correct
- Check rate limits haven't been exceeded
- Ensure you have GPT-4 access (for OpenAI)

### No results
- Check the raw AI response for errors
- Try a different location or topic
- Verify the prompt is well-formed

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Valid API key (Anthropic or OpenAI)

## Support

For issues or questions, check the admin interface tooltips and help sections.