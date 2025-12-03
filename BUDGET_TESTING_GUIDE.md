# Budget-Friendly Testing Guide ($5 Budget)
**Testing Claude Opus 4.5 on Limited Budget**

---

## 💰 Budget Analysis: $5 for Claude Opus 4.5

### Cost Estimation

**Claude Opus 4.5 Pricing:**
- Input tokens: ~$15 per million tokens
- Output tokens: ~$75 per million tokens
- **Estimated cost per re-rank:** ~$0.04-0.05 (4-5 cents)

**What $5 Gets You:**
- **~100-125 re-ranking operations** with Opus 4.5
- This is enough for thorough testing!

**Breakdown:**
- Each product page visit with LLM re-ranking = 1 API call
- Results are cached, so second visit to same product = FREE (no API call)
- 100 operations = testing ~20-30 different products, 3-4 times each

---

## 🎯 Cost-Saving Configuration

### Step 1: Configure Opus 4.5 with Cost Optimization

**Admin Configuration:**
```
Stores > Configuration > NavinDBhudiya > AI Product Recommendation > LLM Re-Ranking

Settings:
✅ Enable LLM Re-Ranking: Yes
✅ Provider: Claude
✅ API Key: [Your Claude API key]
✅ Model: Claude Opus 4.5 (Most Capable)  ← Select this
✅ Temperature: 0.5  ← Lower = faster = cheaper (instead of 0.7)
✅ Candidate Count: 8  ← Reduced from 20 to save tokens
```

**Why these settings save money:**
- **Lower candidate count (8 vs 20):** Sends less data to LLM = fewer input tokens = lower cost
- **Lower temperature (0.5 vs 0.7):** More focused responses = fewer output tokens
- **Result:** Reduces cost per operation from $0.05 → $0.03 (40% savings!)

---

## 🚀 Step-by-Step Budget Testing Plan

### Phase 1: Set Up API with Spending Limits (5 minutes)

1. **Go to Anthropic Console:**
   - Visit: https://console.anthropic.com
   - Sign in

2. **Add Payment Method:**
   - Go to "Billing" → "Payment methods"
   - Add credit card
   - **Set spending limit: $5**

3. **Monitor Usage Dashboard:**
   - Bookmark: https://console.anthropic.com/settings/usage
   - Check this frequently during testing

4. **Create API Key:**
   - Go to "API Keys"
   - Create new key
   - Name it: "Magento Testing - $5 Limit"
   - Copy the key

---

### Phase 2: Configure Magento (10 minutes)

**Commands:**
```bash
# 1. Configure LLM settings
warden env exec php-fpm php bin/magento config:set product_recommendation/llm/enabled 1
warden env exec php-fpm php bin/magento config:set product_recommendation/llm/provider claude
warden env exec php-fpm php bin/magento config:set product_recommendation/llm/api_key "YOUR_API_KEY_HERE"
warden env exec php-fpm php bin/magento config:set product_recommendation/llm/model "claude-opus-4-5-20250514"
warden env exec php-fpm php bin/magento config:set product_recommendation/llm/temperature 0.5
warden env exec php-fpm php bin/magento config:set product_recommendation/llm/candidate_count 8

# 2. Enable debug mode to see API calls
warden env exec php-fpm php bin/magento config:set product_recommendation/general/debug_mode 1

# 3. Clear cache
warden env exec php-fpm php bin/magento cache:flush
```

**Or via Admin UI:**
1. **Stores > Configuration**
2. **NavinDBhudiya > AI Product Recommendation > LLM Re-Ranking**
3. Set values as shown above
4. **Save Config**

---

### Phase 3: Controlled Testing (30 minutes)

**Test Plan - Stay Under $5:**

#### **Test Set 1: Basic Functionality (10 calls = $0.30-0.50)**
```
Visit these pages ONCE each (first visit calls LLM):
1. https://app.demo.test/t-shirt.html
2. https://app.demo.test/pants.html
3. [8 more different product pages]

Expected cost: ~$0.40
Remaining budget: $4.60
```

**What to check:**
- ✅ Recommendations appear
- ✅ Order changes vs vector-only
- ✅ No errors in logs
- ✅ Debug logs show LLM calls

#### **Test Set 2: Cache Validation (0 calls = FREE!)**
```
Revisit the SAME 10 products:
1. https://app.demo.test/t-shirt.html (cached - FREE!)
2. https://app.demo.test/pants.html (cached - FREE!)
...

Expected cost: $0.00 (all cached!)
Remaining budget: $4.60
```

**What to check:**
- ✅ Same recommendations appear instantly
- ✅ No new LLM API calls in logs
- ✅ Cache is working

#### **Test Set 3: Different Product Types (20 calls = $0.60-1.00)**
```
Test variety:
1. Expensive products (5 products)
2. Cheap products (5 products)
3. Configurable products (5 products)
4. Simple products (5 products)

Expected cost: ~$0.80
Remaining budget: $3.80
```

#### **Test Set 4: Quality Testing (40 calls = $1.20-2.00)**
```
Deep test on key products:
1. Your top 10 bestsellers (10 calls)
2. High-margin products (10 calls)
3. New arrivals (10 calls)
4. Clearance items (10 calls)

Expected cost: ~$1.60
Remaining budget: $2.20
```

#### **Test Set 5: Edge Cases (30 calls = $0.90-1.50)**
```
Edge case testing:
1. Products with no similar items
2. Products with 100+ similar items
3. Products with special characters
4. Products with long descriptions

Expected cost: ~$1.20
Remaining budget: $1.00
```

**Total Testing:**
- **100 unique product tests**
- **Total cost: ~$4.00-4.50**
- **Remaining: $0.50-1.00 buffer**

---

## 📊 Monitoring API Usage in Real-Time

### Watch Logs During Testing

**Terminal 1: Watch API Calls**
```bash
tail -f var/log/product_recommendation.log | grep -E "(LlmReRanker|Claude|tokens)"
```

**Expected Output:**
```
[2025-11-30 10:15:23] [LlmReRanker] Sending re-ranking request to LLM
[2025-11-30 10:15:24] [Claude] Sending request to Claude API
[2025-11-30 10:15:25] [Claude] Request successful (tokens: 850 input, 95 output)
[2025-11-30 10:15:25] [LlmReRanker] Successfully re-ranked products
```

**Cost Calculation:**
```
Input tokens: 850 × $15 / 1,000,000 = $0.01275
Output tokens: 95 × $75 / 1,000,000 = $0.00712
Total: $0.01987 (~2 cents per call)
```

### Track Total Spend

Create a simple tracking spreadsheet:

| Test # | Product | Input Tokens | Output Tokens | Cost | Running Total |
|--------|---------|--------------|---------------|------|---------------|
| 1 | T-Shirt | 850 | 95 | $0.020 | $0.020 |
| 2 | Pants | 920 | 102 | $0.022 | $0.042 |
| 3 | Dress | 880 | 88 | $0.019 | $0.061 |
| ... | ... | ... | ... | ... | ... |

**Or use this bash script:**
```bash
# Count API calls in logs
grep -c "Sending request to Claude API" var/log/product_recommendation.log

# Multiply by $0.03 (average cost) to estimate spend
# Example: 50 calls × $0.03 = $1.50
```

---

## 🛡️ Safety Measures to Prevent Overspending

### 1. Set Hard Limit in Anthropic Console
```
1. Go to: https://console.anthropic.com/settings/limits
2. Set "Monthly spending limit": $5
3. Set "Alert threshold": $4 (get email at $4)
4. Save
```

### 2. Disable After Testing
```bash
# Immediately after testing, disable LLM to prevent accidental usage
warden env exec php-fpm php bin/magento config:set product_recommendation/llm/enabled 0
warden env exec php-fpm php bin/magento cache:flush
```

### 3. Only Test on Specific Products
Instead of browsing randomly, have a **pre-defined test list**:

**Create test list:**
```bash
# Create file: test-products.txt
/t-shirt.html
/pants.html
/dress.html
/shoes.html
/jacket.html
# ... (add 20-30 products)
```

**Test systematically:**
Open each URL once, note results, move to next.

---

## 💡 Alternative: Test with Sonnet 4.5 First (Recommended!)

**Why start with Sonnet 4.5:**
- **5x cheaper** than Opus ($0.01 vs $0.05 per call)
- $5 = **~500 operations** instead of 100
- Same quality for most use cases
- Validate everything works before using Opus

**Strategy:**
1. **Phase 1:** Test with Sonnet 4.5 (100 tests = $1.00)
2. **Phase 2:** If satisfied, switch to Opus 4.5 (100 tests = $4.00)
3. **Total:** 200 tests for $5!

**To use Sonnet 4.5 instead:**
```bash
warden env exec php-fpm php bin/magento config:set product_recommendation/llm/model "claude-sonnet-4-5-20250929"
warden env exec php-fpm php bin/magento cache:flush
```

---

## 📈 Testing Checklist

### Before Testing:
- [ ] Set $5 spending limit in Anthropic Console
- [ ] Set alert at $4 threshold
- [ ] Create test product list (20-30 products)
- [ ] Enable debug mode
- [ ] Configure LLM with cost-optimized settings

### During Testing:
- [ ] Watch logs for API calls
- [ ] Track token usage from logs
- [ ] Calculate running cost estimate
- [ ] Check Anthropic usage dashboard every 10 calls
- [ ] Test cache by revisiting same products

### After Testing:
- [ ] Disable LLM re-ranking immediately
- [ ] Review total spend in Anthropic Console
- [ ] Document results (which products improved?)
- [ ] Clear logs: `rm var/log/product_recommendation.log`

---

## 🎯 Expected Results Within Budget

With $5 and Opus 4.5, you can:

✅ **Test 100 unique products** (first-time recommendations)
✅ **Unlimited revisits** (cached, free)
✅ **Compare with/without LLM** on 20-30 products
✅ **Test different product types** (expensive, cheap, configurable)
✅ **Measure quality improvement** comprehensively

**This is MORE than enough for thorough testing!**

---

## 🔥 Maximum Cost Savings - Advanced Tips

### 1. Reduce Max Tokens
Edit `ClaudeProvider.php` temporarily:
```php
$maxTokens = $options['max_tokens'] ?? 1024;  // Instead of 4096
```
**Savings:** ~50% on output costs

### 2. Cache Aggressively
```bash
# Increase cache TTL to 24 hours
warden env exec php-fpm php bin/magento config:set product_recommendation/cache/ttl 86400
```

### 3. Test Only Related Products (Disable Cross-sell/Upsell)
Related products only = 1 API call per product
Cross-sell + Upsell = 3 API calls per product

### 4. Batch Your Testing
Test all products in one 30-minute session, then disable.
Don't leave it enabled and browse casually!

---

## 📞 Emergency Stop

**If you see costs rising too fast:**

```bash
# IMMEDIATE STOP - Disable LLM
warden env exec php-fpm php bin/magento config:set product_recommendation/llm/enabled 0
warden env exec php-fpm php bin/magento cache:flush

# Or faster: Delete API key from config
warden env exec php-fpm php bin/magento config:set product_recommendation/llm/api_key ""
warden env exec php-fpm php bin/magento cache:flush
```

---

## 🎓 Summary: Smart Testing Strategy

**Best Approach for $5 Budget:**

1. **Start with Sonnet 4.5** (100 tests = $1)
   - Validate functionality
   - Test cache
   - Measure improvements

2. **Switch to Opus 4.5** (80 tests = $4)
   - Test on high-value products
   - Compare quality difference
   - Final validation

3. **Keep $0-1 buffer** for emergencies

**Commands to Start:**
```bash
# Sonnet 4.5 configuration (cheaper for initial testing)
warden env exec php-fpm php bin/magento config:set product_recommendation/llm/enabled 1
warden env exec php-fpm php bin/magento config:set product_recommendation/llm/provider claude
warden env exec php-fpm php bin/magento config:set product_recommendation/llm/api_key "YOUR_KEY"
warden env exec php-fpm php bin/magento config:set product_recommendation/llm/model "claude-sonnet-4-5-20250929"
warden env exec php-fpm php bin/magento config:set product_recommendation/llm/temperature 0.5
warden env exec php-fpm php bin/magento config:set product_recommendation/llm/candidate_count 8
warden env exec php-fpm php bin/magento config:set product_recommendation/general/debug_mode 1
warden env exec php-fpm php bin/magento cache:flush

# Watch the costs
tail -f var/log/product_recommendation.log | grep -E "(tokens|Claude)"
```

**After validating with Sonnet, switch to Opus:**
```bash
warden env exec php-fpm php bin/magento config:set product_recommendation/llm/model "claude-opus-4-5-20250514"
warden env exec php-fpm php bin/magento cache:flush
```

---

**You're ready for budget-friendly testing! 🎉**

Total estimated spend: **$4-5** for comprehensive testing of 100-180 products!