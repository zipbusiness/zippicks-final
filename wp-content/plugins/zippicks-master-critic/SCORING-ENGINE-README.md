# ZipPicks Master Critic Scoring Engine

## Overview

The `ZP_MasterCritic_ScoringEngine` class provides deterministic, review-weighted scoring across six editorial pillars for restaurants. It generates consistent scores and credible editorial summaries based on aggregated review data from multiple sources.

## Features

- **Multi-source aggregation**: Combines scores from Yelp (50%), Google (30%), and TripAdvisor (20%)
- **Confidence-based adjustments**: Modifies scores based on review volume
- **Six pillar scoring**: Distributes scores across food quality, service, atmosphere, value, consistency, and cultural relevance
- **Editorial summary generation**: Creates 3-4 sentence summaries with authentic editorial tone
- **Deterministic results**: Same input always produces same output

## Usage

```php
require_once 'includes/ScoringEngine.php';

$engine = new ZP_MasterCritic_ScoringEngine();

$restaurant_data = [
    [
        'name' => 'Restaurant Name',
        'price_tier' => '$$',
        'top_dishes' => ['Signature Dish', 'Popular Item'],
        'sources' => [
            'yelp' => ['score' => 4.5, 'reviews' => 300],
            'google' => ['score' => 4.3, 'reviews' => 200],
            'tripadvisor' => ['score' => 4.4, 'reviews' => 100]
        ],
        'reviews' => [
            // Array of review objects
        ]
    ]
];

$results = $engine->calculate_scores($restaurant_data);
```

## Scoring Algorithm

### Base Score Calculation
1. Normalizes source scores to 10-point scale (multiply by 2)
2. Applies weighted average:
   - Yelp: 50%
   - Google: 30%
   - TripAdvisor: 20%

### Confidence Modifiers
- < 100 reviews: -0.3 penalty
- 100-499 reviews: no modifier
- 500+ reviews: +0.1 bonus

### Pillar Distribution
Each pillar receives a multiplier of the base score:
- Food Quality: 1.05x
- Service: 0.95x
- Atmosphere & Design: 1.00x
- Value: 0.90x
- Consistency: 1.00x
- Cultural Relevance: 1.00x

All scores are capped at 10.0 and rounded to 1 decimal place.

## Editorial Summary Generation

The engine creates authentic-sounding summaries using:
- **Emotional anchors**: "feels like a secret", "operates with quiet confidence"
- **Specific dish callouts**: References from top_dishes and review content
- **Vibe descriptors**: "cozy", "intimate", "vibrant", etc.
- **Impact phrases**: "keeps locals coming back", "turns first-timers into regulars"

## Output Format

```php
[
    'rank' => 1,
    'name' => 'Restaurant Name',
    'score' => 9.2,
    'review_count' => 600,
    'price_tier' => '$$',
    'summary' => 'Editorial summary text...',
    'top_dishes' => ['Dish 1', 'Dish 2'],
    'pillar_scores' => [
        'food_quality' => 9.7,
        'service' => 8.7,
        'atmosphere_design' => 9.2,
        'value' => 8.3,
        'consistency' => 9.2,
        'cultural_relevance' => 9.2
    ],
    'confidence' => 'high'
]
```

## Integration Examples

### WordPress Integration
See `class-master-critic-integration.php` for examples of:
- Generating Top 10 lists
- Saving as WordPress posts
- REST API endpoints
- AJAX handlers
- Structured data generation

### Testing
Run `test-scoring-engine.php` to see example output with sample restaurant data.

## Key Principles

1. **Deterministic**: Same input always produces same output
2. **Transparent**: Clear scoring methodology with documented weights
3. **Editorial**: Summaries sound human-written, not AI-generated
4. **Scalable**: Efficient algorithm suitable for batch processing
5. **Extensible**: Easy to add new verticals beyond restaurants