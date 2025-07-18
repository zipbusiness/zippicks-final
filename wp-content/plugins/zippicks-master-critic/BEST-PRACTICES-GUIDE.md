# ZipPicks Master Critic - Best Practices Guide

## 🎯 Delivering the Best Curated Recommendations

### 1. **AI Model Selection**
- **Production**: Use **Claude 3 Opus** for highest quality
- **Development**: Use **Claude 3 Sonnet** for cost-effective testing
- **Bulk Operations**: Use **Claude 3 Haiku** for high-volume generation

### 2. **Prompt Optimization**

#### Location Specificity
```
✅ GOOD: "Italian restaurants in Downtown Los Angeles"
❌ BAD: "Italian restaurants in LA area"

✅ GOOD: "Coffee shops in Williamsburg, Brooklyn"
❌ BAD: "Coffee shops in NYC"
```

#### Topic Precision
```
✅ GOOD: "Neapolitan pizza specialists"
✅ GOOD: "Farm-to-table restaurants with tasting menus"
✅ GOOD: "Late-night ramen shops open after midnight"

❌ BAD: "Good pizza places"
❌ BAD: "Nice restaurants"
```

### 3. **Quality Filters**

Set minimum thresholds:
- Overall Score: **7.5+** (only include excellent places)
- Individual Pillars: **6.0+** (no major weaknesses)
- Review Count: **100+** (established businesses)

### 4. **Vibe Curation**

Focus on ZipPicks' unique vibe taxonomy:
- **Date Night** (not just "romantic")
- **Natural Wine** (specific scene)
- **Dog-Friendly** (lifestyle match)
- **Late Night** (specific need)
- **Instagram-Worthy** (social currency)

### 5. **Business Verification**

Always verify:
- ✅ Business is actually in the specified location
- ✅ Currently operating (not permanently closed)
- ✅ Matches the requested category/vibe
- ✅ Has legitimate reviews and presence

### 6. **Content Enhancement**

For each business, ensure:
1. **Compelling Summary** - 2-3 sentences that capture the essence
2. **Specific Highlights** - Actual dishes/features, not generic
3. **Insider Tips** - What locals know
4. **Vibe Match** - Why it fits the search

### 7. **Common Pitfalls to Avoid**

❌ **Don't Include**:
- Chain restaurants (unless specifically requested)
- Businesses from neighboring cities
- Places with scores below 7.5
- Generic descriptions ("great food", "nice atmosphere")

✅ **Do Include**:
- Hidden gems with passionate followings
- New spots with buzz (even if fewer reviews)
- Diverse price points
- Specific details (dish names, chef names, unique features)

### 8. **Testing Your Prompts**

Before going live:
1. Test with major cities (LA, NYC, Chicago)
2. Test with smaller cities
3. Test with specific neighborhoods
4. Test edge cases (late night, dietary restrictions)

### 9. **Monitoring Quality**

Track these metrics:
- AI generation success rate
- Business verification pass rate
- Average quality scores
- User engagement with recommendations

### 10. **Continuous Improvement**

1. **Save successful prompts** as templates
2. **Analyze failed generations** to improve prompts
3. **Update location validator** with new cities
4. **Refine vibe mappings** based on usage

## 🚀 Quick Start Checklist

- [ ] Set Claude 3 Opus as default model
- [ ] Configure minimum score thresholds
- [ ] Test location validator with your city
- [ ] Create category-specific prompt templates
- [ ] Set up quality monitoring
- [ ] Train team on vibe taxonomy
- [ ] Document successful patterns

## 📊 Expected Results

With these best practices:
- **90%+** accurate location matching
- **8.0+** average business scores
- **95%+** business verification rate
- **3-5** relevant vibes per business
- **<1%** chain restaurant inclusion