# Master Critic Enhancement - Engineering Handoff

## Overview
This enhancement package dramatically improves the Master Critic's first-pass recommendation quality through:
1. Enhanced prompting with city-specific context
2. Multi-stage validation
3. Confidence scoring
4. Quality testing framework

## Implementation Steps

### 1. Update AI Service (Immediate - 30 minutes)
Replace the current `generate_prompt()` method in `class-ai-service.php` with the enhanced version:

```php
// In class-ai-service.php, update the generate_prompt method:
public function generate_prompt($params) {
    // Use the new enhanced prompt system
    require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-ai-service-enhanced.php';
    $enhanced_service = new ZipPicks_Master_Critic_AI_Service_Enhanced();
    return $enhanced_service->build_enhanced_prompt($params);
}
```

### 2. Add City Context System (1 hour)
1. Include the new `class-city-context.php` file
2. Update the AI service to use city-specific contexts
3. Add neighborhood validation to prevent suburban listings

### 3. Update Default Prompt Template (Immediate - 10 minutes)
Replace the current prompt template in the database with the enhanced version from `enhanced-master-prompt.txt`

```sql
UPDATE wp_zippicks_prompt_templates 
SET prompt_template = '[ENHANCED_PROMPT_CONTENT]'
WHERE business_category = 'all' AND is_default = 1;
```

### 4. Switch to Claude-3-Opus (Immediate - 5 minutes)
Update the default model in settings:
```php
update_option('zippicks_anthropic_model', 'claude-3-opus-20240229');
```

### 5. Add Confidence Scoring (2 hours)
Implement the validation and confidence system:
- Add `confidence_score` field to generations table
- Display confidence in admin UI
- Route low-confidence results to human review

## Key Improvements

### 1. **Prompt Engineering**
- **Before**: Generic location enforcement
- **After**: City-specific cultural context, neighborhood validation, local expertise simulation

### 2. **Quality Thresholds**
- **Before**: Any score accepted
- **After**: 8.0+ minimum, city-adjusted thresholds

### 3. **Validation**
- **Before**: Trust AI output
- **After**: Validate neighborhoods, score consistency, business count

### 4. **Model Selection**
- **Before**: Default to Sonnet for cost
- **After**: Use Opus for critical first-pass, cache aggressively

## Testing Protocol

### 1. Known Good Test Cases
Test with businesses we know should appear:

```php
// New York Pizza Test
$test_cases = array(
    'new york' => array(
        'pizza' => ['Prince Street Pizza', 'Joe\'s', 'Lucali', 'Patsy\'s'],
        'bagel' => ['Russ & Daughters', 'H&H', 'Black Seed', 'Ess-a-Bagel']
    ),
    'austin' => array(
        'bbq' => ['Franklin', 'la Barbecue', 'Micklethwait', 'Kerlin'],
        'mexican' => ['Suerte', 'Veracruz', 'El Alma', 'Matt\'s El Rancho']
    )
);
```

### 2. Quality Metrics to Track
- **Accuracy**: % of expected businesses that appear
- **Precision**: % of results that are actually in the city
- **Relevance**: % that locals would agree belong in top 10
- **Freshness**: % currently operating vs closed

## Cost Optimization

### 1. **Smart Caching**
- Cache for 7 days (not 1 hour) for stable queries
- Cache component parts (descriptions) separately
- Implement partial cache hits

### 2. **Tiered Generation**
```php
if ($city_population > 1000000) {
    $model = 'claude-3-opus-20240229';  // Best quality for major cities
} elseif ($city_population > 100000) {
    $model = 'claude-3-sonnet-20240229'; // Good quality for mid-size
} else {
    $model = 'gpt-4-turbo-preview';      // Cost-effective for small
}
```

## Monitoring & Iteration

### 1. **Track Success Metrics**
- Local validator approval rate
- User engagement with recommendations
- Business owner disputes
- Geographic accuracy

### 2. **Continuous Improvement**
- A/B test prompt variations
- Build city-specific template library
- Create feedback loop from validators

## Next Steps

1. **Immediate** (Today):
   - Update prompt template in database
   - Switch to Claude-3-Opus for generation
   - Implement basic confidence scoring

2. **This Week**:
   - Add city context system
   - Implement neighborhood validation
   - Create quality testing framework

3. **Next Sprint**:
   - Build validator feedback API
   - Implement learning system
   - Add real-time business verification

## Success Criteria

The Master Critic should achieve:
- **90%+ accuracy** on known-good businesses in major cities
- **95%+ geographic accuracy** (no suburban leakage)
- **80%+ validator approval** on first pass
- **<5% dispute rate** from business owners

This enhancement positions ZipPicks to deliver unmatched recommendation quality from day one, with a clear path to continuous improvement based on real-world validation.