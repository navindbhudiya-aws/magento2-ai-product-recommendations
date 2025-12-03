# Complete Testing Logs Guide

**What You'll See When Testing LLM Re-Ranking**

---

## 📊 Log File Location

```bash
tail -f var/log/product_recommendation.log
```

---

## ✅ Expected Logs for Successful Request

When you visit a product page with LLM re-ranking enabled, you'll see this complete flow:

###Step 1: Re-Ranking Process Starts
```log
[ProductRecommendation][LlmReRanker] 🚀 LLM Re-Ranking Started
{
    "source_product_id": "1234",
    "source_product_name": "Red Cotton T-Shirt",
    "candidate_count": 15,
    "recommendation_type": "related",
    "customer_id": 42,
    "requested_limit": 8
}
```
**✅ What this means:** LLM re-ranking process has started for product ID 1234

---

### Step 2: Configuration Check
```log
[ProductRecommendation][LlmReRanker] ✅ LLM re-ranking is ENABLED
```
**✅ What this means:** Feature is turned on in admin configuration

---

### Step 3: Provider Validation
```log
[ProductRecommendation][LlmReRanker] ✅ LLM Provider Ready
{
    "provider": "claude",
    "model": "claude-opus-4-5-20250514"
}
```
**✅ What this means:** Claude API is configured and ready to use

---

### Step 4: Candidates Prepared
```log
[ProductRecommendation][LlmReRanker] 📋 Prepared Candidates for Re-ranking
{
    "total_candidates": 15,
    "sending_to_llm": 10,
    "configured_limit": 10
}
```
**✅ What this means:** Out of 15 similar products found, we're sending 10 to Claude for re-ranking

---

### Step 5: Candidate Products Listed
```log
[ProductRecommendation][LlmReRanker] 📦 Candidate Products
{
    "products": [
        "1235:Blue Cotton T-Shirt",
        "1236:Red Polyester Shirt",
        "1237:White Cotton Tee",
        ...
    ]
}
```
**✅ What this means:** These are the products being sent to Claude for intelligent re-ordering

---

### Step 6: Building Prompt
```log
[ProductRecommendation][LlmReRanker] 🔨 Building LLM prompt with context...
[ProductRecommendation][LlmReRanker] ✅ Prompt Built Successfully
{
    "prompt_length": 2450,
    "estimated_tokens": 612
}
```
**✅ What this means:** Created a detailed prompt with product context, customer info, and seasonal data

---

### Step 7: LLM Configuration
```log
[ProductRecommendation][LlmReRanker] 🌡️  LLM Configuration
{
    "temperature": 0.7,
    "max_tokens": 4096,
    "candidate_count": 10
}
```
**✅ What this means:** Using temperature 0.7 (balanced creativity) with up to 4096 tokens for response

---

### Step 8: Sending to Claude API
```log
[ProductRecommendation][LlmReRanker] 📤 Sending request to LLM API...
{
    "provider": "claude",
    "model": "claude-opus-4-5-20250514"
}

[ProductRecommendation][Claude] 📡 Sending request to Claude API
{
    "model": "claude-opus-4-5-20250514",
    "temperature": 0.7,
    "max_tokens": 4096,
    "prompt_length": 2450,
    "estimated_input_tokens": 612,
    "api_endpoint": "https://api.anthropic.com/v1/messages",
    "api_version": "2023-06-01"
}
```
**✅ What this means:** Making HTTP request to Claude API with your prompt

---

### Step 9: Claude API Response (SUCCESS!)
```log
[ProductRecommendation][Claude] ✅ Received response from Claude API
{
    "model_used": "claude-opus-4-5-20250514",
    "response_length": 850,
    "input_tokens": 620,
    "output_tokens": 102,
    "total_tokens": 722,
    "stop_reason": "end_turn",
    "request_duration_ms": 1450.23,
    "estimated_cost_usd": "$0.0037",
    "response_preview": "{\"rankings\":[{\"product_id\":1236,\"rank\":1,\"reason\":\"Perfect complement, same material, seasonal fit\"}...]"
}
```
**✅ What this means:**
- Claude API responded successfully in 1.45 seconds
- Used 620 input tokens + 102 output tokens = 722 total
- **Cost: $0.0037** (less than half a cent!)
- Response looks like valid JSON

---

### Step 10: Response Received
```log
[ProductRecommendation][LlmReRanker] 📥 Received response from LLM
{
    "response_length": 850,
    "response_preview": "{\"rankings\":[{\"product_id\":1236,\"rank\":1,\"reason\":\"Perfect complement..."
}
```
**✅ What this means:** Got the response back from Claude

---

### Step 11: Parsing JSON
```log
[ProductRecommendation][LlmReRanker] 🔍 Parsing LLM JSON response...
[ProductRecommendation][LlmReRanker] ✅ Parsed Rankings Successfully
{
    "ranking_count": 10,
    "rankings": [
        {"product_id": "1236", "rank": "1", "reason": "Perfect complement, same material, seasonal fit"},
        {"product_id": "1240", "rank": "2", "reason": "Frequently bought together, matches price point"},
        {"product_id": "1235", "rank": "3", "reason": "Good alternative, has discount for value"}
    ]
}
```
**✅ What this means:** Claude provided intelligent rankings with reasons for each position!

---

### Step 12: Applying Rankings
```log
[ProductRecommendation][LlmReRanker] 🔄 Applying LLM rankings to candidates...
[ProductRecommendation][LlmReRanker] ✅ Successfully re-ranked products
{
    "original_count": 10,
    "reranked_count": 10,
    "order_changed": true,
    "before_order": [1235, 1236, 1237, 1238, 1239],
    "after_order": [1236, 1240, 1235, 1238, 1242]
}
```
**✅ What this means:** Order changed! Claude moved product 1236 to #1, 1240 to #2, etc.

---

### Step 13: Final Results
```log
[ProductRecommendation][LlmReRanker] 🎯 Final Results
{
    "returned_count": 8,
    "product_ids": [1236, 1240, 1235, 1238, 1242, 1244, 1237, 1248]
}
```
**✅ What this means:** These 8 products will be displayed to customer in this order

---

## ❌ Error Scenarios

### Scenario 1: LLM Disabled
```log
[ProductRecommendation][LlmReRanker] 🚀 LLM Re-Ranking Started {...}
[ProductRecommendation][LlmReRanker] ⚠️  LLM re-ranking is DISABLED in configuration - using vector similarity only
```
**🔧 Fix:** Enable in Admin > Stores > Configuration > LLM Re-Ranking > Enable: Yes

---

### Scenario 2: No API Key
```log
[ProductRecommendation][LlmReRanker] ✅ LLM re-ranking is ENABLED
[ProductRecommendation][LlmReRanker] ❌ LLM provider not available, skipping re-ranking
{
    "configured_provider": "claude",
    "has_api_key": false
}
```
**🔧 Fix:** Add Claude API key in Admin configuration

---

### Scenario 3: Invalid API Key
```log
[ProductRecommendation][Claude] 📡 Sending request to Claude API {...}
[ProductRecommendation][Claude] ❌ Claude API HTTP Error
{
    "error": "401 Unauthorized",
    "error_body": "{\"error\":{\"type\":\"authentication_error\",\"message\":\"invalid x-api-key\"}}",
    "status_code": 401
}
```
**🔧 Fix:** Check API key is correct, starts with `sk-ant-`

---

### Scenario 4: Rate Limit / Quota Exceeded
```log
[ProductRecommendation][Claude] ❌ Claude API HTTP Error
{
    "error": "429 Too Many Requests",
    "error_body": "{\"error\":{\"type\":\"rate_limit_error\",\"message\":\"Rate limit exceeded\"}}",
    "status_code": 429
}
```
**🔧 Fix:** Wait a moment or check Anthropic Console for quota

---

### Scenario 5: High Cost Warning
```log
[ProductRecommendation][Claude] ✅ Received response from Claude API {...}
[ProductRecommendation][Claude] ⚠️  HIGH COST WARNING
{
    "cost": "$0.1250",
    "input_tokens": 3500,
    "output_tokens": 800
}
```
**⚠️ Action:** This request cost more than 10 cents - check why prompt is so large

---

## 📝 What to Watch For

### ✅ Success Indicators:
1. See `🚀 LLM Re-Ranking Started`
2. See `✅ LLM Provider Ready`
3. See `📡 Sending request to Claude API`
4. See `✅ Received response from Claude API`
5. See `estimated_cost_usd` in the response
6. See `order_changed: true`

### ❌ Problem Indicators:
1. See `❌` emoji anywhere
2. See `⚠️ LLM re-ranking is DISABLED`
3. See `has_api_key: false`
4. See `status_code: 401` or `429`
5. See `Failed to parse` messages

---

## 🎯 Example: Complete Successful Test

```bash
# Watch logs
tail -f var/log/product_recommendation.log | grep -E "(LlmReRanker|Claude)"
```

**When you visit https://app.demo.test/t-shirt.html**

```log
[2025-11-30 10:15:20] [ProductRecommendation][LlmReRanker] 🚀 LLM Re-Ranking Started
[2025-11-30 10:15:20] [ProductRecommendation][LlmReRanker] ✅ LLM re-ranking is ENABLED
[2025-11-30 10:15:20] [ProductRecommendation][LlmReRanker] ✅ LLM Provider Ready {"provider":"claude","model":"claude-opus-4-5-20250514"}
[2025-11-30 10:15:20] [ProductRecommendation][LlmReRanker] 📋 Prepared Candidates for Re-ranking
[2025-11-30 10:15:20] [ProductRecommendation][LlmReRanker] 📦 Candidate Products
[2025-11-30 10:15:20] [ProductRecommendation][LlmReRanker] 🔨 Building LLM prompt with context...
[2025-11-30 10:15:20] [ProductRecommendation][LlmReRanker] ✅ Prompt Built Successfully
[2025-11-30 10:15:20] [ProductRecommendation][LlmReRanker] 🌡️  LLM Configuration
[2025-11-30 10:15:20] [ProductRecommendation][LlmReRanker] 📤 Sending request to LLM API...
[2025-11-30 10:15:20] [ProductRecommendation][Claude] 📡 Sending request to Claude API
[2025-11-30 10:15:22] [ProductRecommendation][Claude] ✅ Received response from Claude API {"input_tokens":620,"output_tokens":102,"estimated_cost_usd":"$0.0037"}
[2025-11-30 10:15:22] [ProductRecommendation][LlmReRanker] 📥 Received response from LLM
[2025-11-30 10:15:22] [ProductRecommendation][LlmReRanker] 🔍 Parsing LLM JSON response...
[2025-11-30 10:15:22] [ProductRecommendation][LlmReRanker] ✅ Parsed Rankings Successfully
[2025-11-30 10:15:22] [ProductRecommendation][LlmReRanker] 🔄 Applying LLM rankings to candidates...
[2025-11-30 10:15:22] [ProductRecommendation][LlmReRanker] ✅ Successfully re-ranked products {"order_changed":true}
[2025-11-30 10:15:22] [ProductRecommendation][LlmReRanker] 🎯 Final Results
```

**✅ PERFECT! Everything worked!**
- Request took 2 seconds
- Cost: $0.0037 (less than half a cent)
- Order was changed by Claude's intelligence
- Products are now ranked better!

---

## 💰 Cost Tracking

Watch for this line in logs:
```log
"estimated_cost_usd": "$0.0037"
```

**Running Total:**
- Request 1: $0.0037
- Request 2: $0.0042
- Total so far: $0.0079 (out of $5.00 budget)

---

## 🔍 Debugging Commands

### Watch all LLM activity:
```bash
tail -f var/log/product_recommendation.log | grep -E "(LlmReRanker|Claude)"
```

### Watch only successful API calls:
```bash
tail -f var/log/product_recommendation.log | grep "✅ Received response from Claude API"
```

### Watch only costs:
```bash
tail -f var/log/product_recommendation.log | grep "estimated_cost_usd"
```

### Watch only errors:
```bash
tail -f var/log/product_recommendation.log | grep -E "(❌|⚠️)"
```

### Count total API calls made:
```bash
grep -c "Sending request to Claude API" var/log/product_recommendation.log
```

---

## ✅ Confirmation Checklist

After testing, you should see:

- [x] `🚀 LLM Re-Ranking Started` (process initiated)
- [x] `✅ LLM Provider Ready` (Claude is configured)
- [x] `📡 Sending request to Claude API` (API call made)
- [x] `input_tokens` and `output_tokens` (token usage)
- [x] `estimated_cost_usd` (cost tracking)
- [x] `request_duration_ms` (performance)
- [x] `order_changed: true` (ranking actually changed)
- [x] `🎯 Final Results` (products returned)

**If you see all of these, your LLM re-ranking is working perfectly!** ✅

---

**Log Guide Created: 2025-11-30**
**Module: NavinDBhudiya_ProductRecommendation**