# Master Critic Enhancement - Quick Installation Guide

## 🚀 Quick Start (5 minutes)

### Step 1: Update Database
The plugin will automatically update the database schema when you visit the admin area. The new fields (`confidence_score` and `validation_report`) will be added to the generations table.

### Step 2: Update Default Model
Run from command line:
```bash
cd wp-content/plugins/zippicks-master-critic/
php update-default-model.php
```

Or manually in WordPress Admin → Master Critic → Settings:
- Change "Anthropic Model" to `claude-3-opus-20240229`

### Step 3: Update Prompt Template
Run from command line:
```bash
php update-prompt-template.php
```

Or manually:
1. Go to WordPress Admin → Master Critic → Templates
2. Edit the default template for "All Categories"
3. Replace with content from `includes/prompts/enhanced-master-prompt.txt`

## ✅ Verification

### 1. Test Generation
1. Go to WordPress Admin → Master Critic
2. Generate a list for "New York" + "Pizza"
3. You should see:
   - Confidence score displayed (aim for 85%+)
   - Known pizzerias like Prince Street Pizza, Joe's, Lucali
   - No businesses from New Jersey or Connecticut

### 2. Run Quality Test
Visit: `/wp-content/plugins/zippicks-master-critic/test-quality.php`
- Test major cities
- Verify 90%+ accuracy on known businesses

### 3. Check Caching
Generate the same list twice:
- First time: Takes 5-10 seconds
- Second time: Instant (cached)

## 🎯 Success Indicators

✅ **Good Signs:**
- Confidence scores consistently above 85%
- Recognizable local favorites appearing
- No suburban businesses in city searches
- Fast response times on repeated queries

⚠️ **Warning Signs:**
- Confidence scores below 70%
- Unknown or closed businesses appearing
- Businesses from wrong locations
- Validation warnings appearing frequently

## 💡 Pro Tips

1. **Start with Major Cities** - They have the best data and highest confidence
2. **Monitor API Usage** - Check your Anthropic dashboard for costs
3. **Review Low Confidence Results** - Send to human validators
4. **Cache Popular Queries** - Pre-generate common city/category combos

## 🔧 Troubleshooting

**Low Confidence Scores:**
- Check if city name is spelled correctly
- Verify category matches available data
- Try a more specific topic

**Wrong Businesses:**
- Check validation warnings
- Verify location boundaries
- Report false positives for training

**Slow Performance:**
- Check cache is working (Redis/Memcached)
- Verify API keys are valid
- Monitor rate limits

## 📊 Metrics to Track

Daily:
- Average confidence score
- Cache hit rate
- API costs

Weekly:
- Quality test results
- User feedback
- Business owner disputes

## 🚨 Emergency Contacts

- API Issues: Check Anthropic status page
- Plugin Errors: Check WordPress debug.log
- Quality Issues: Run test-quality.php

---

**Remember:** The goal is 90%+ accuracy with 95%+ geographic precision. If you're not hitting these targets, review the validation warnings and adjust accordingly.