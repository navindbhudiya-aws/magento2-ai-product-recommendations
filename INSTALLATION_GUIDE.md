# Hybrid Database Storage - Installation & Testing Guide

## ✅ Completed Implementation

### 1. Database Schema
- **File**: `etc/db_schema.xml`
- **Table**: `product_recommendation_llm_ranking`
- **Columns**: id, customer_id, product_id, recommendation_type, store_id, ranked_product_ids (JSON), ranking_metadata (JSON), created_at, expires_at, model_used, estimated_cost
- **Indexes**: Optimized for lookup by (customer_id, product_id, type, store)
- **Foreign Keys**: Links to customer_entity and store tables

### 2. Models & Repository
- **Data Interface**: `Api/Data/LlmRankingInterface.php`
- **Model**: `Model/LlmRanking.php` - Handles JSON encoding/decoding
- **ResourceModel**: `Model/ResourceModel/LlmRanking.php` - Database operations
- **Collection**: `Model/ResourceModel/LlmRanking/Collection.php` - Query filtering
- **Repository**: `Model/LlmRankingRepository.php` - Business logic layer

### 3. Service Updates
- **File**: `Service/RecommendationService.php`
- **Updated**:
  - Constructor now injects LlmRankingRepository, CustomerSession, DateTime
  - `getRecommendationsWithScores` method checks database first for logged-in users
  - Added `hydrateFromProductIds` method to rebuild results from database

### 4. Dependency Injection
- **File**: `etc/di.xml`
- **Added**:
  - Preference mappings for LlmRankingInterface and LlmRankingRepositoryInterface
  - Logger injection for LlmRankingRepository

## 🟡 Remaining Task

### **Add Database Save Logic**

The final step is to update the cache save section (after line 450 in RecommendationService.php) to ALSO save to database for logged-in customers.

**Location**: `Service/RecommendationService.php` line ~452

**Replace this section:**
```php
// Cache results (includes LLM re-ranked results!)
if ($this->config->isCacheEnabled() && !empty($results)) {
    $cacheData = $this->dehydrateResults($results);
    $this->cache->save(
        $this->serializer->serialize($cacheData),
        $cacheKey,
        [RecommendationCache::CACHE_TAG],
        $this->config->getCacheLifetime()
    );
    $this->log('💾 [CACHED] Saved LLM re-ranked results to cache', [
        'product_id' => $productId,
        'type' => $type,
        'cache_key' => $cacheKey,
        'result_count' => count($results),
        'cache_lifetime' => $this->config->getCacheLifetime() . ' seconds',
        'note' => 'Future page loads will use cached results (no API cost!)'
    ]);
}
```

**With:**
```php
// SAVE RESULTS
if (!empty($results)) {
    $rankedProductIds = array_map(fn($r) => (int)$r->getProduct()->getId(), $results);

    // Save to DATABASE for logged-in customers
    if ($customerId && $this->llmRankingRepository && $this->llmRankingFactory && $this->dateTime) {
        try {
            $expiresAt = gmdate('Y-m-d H:i:s', $this->dateTime->gmtTimestamp() + $this->config->getCacheLifetime());

            /** @var \NavinDBhudiya\ProductRecommendation\Api\Data\LlmRankingInterface $ranking */
            $ranking = $this->llmRankingFactory->create();
            $ranking->setCustomerId($customerId)
                ->setProductId($productId)
                ->setRecommendationType($type)
                ->setStoreId($storeId)
                ->setRankedProductIds($rankedProductIds)
                ->setRankingMetadata([
                    'generated_at' => gmdate('Y-m-d H:i:s'),
                    'result_count' => count($results),
                    'llm_enabled' => $this->config->isLlmRerankingEnabled($storeId)
                ])
                ->setExpiresAt($expiresAt);

            // Extract LLM metadata from results if available
            $metadata = $results[0]->getMetadata() ?? [];
            if (isset($metadata['llm_model'])) {
                $ranking->setModelUsed($metadata['llm_model']);
            }
            if (isset($metadata['llm_cost'])) {
                $ranking->setEstimatedCost((float)$metadata['llm_cost']);
            }

            $this->llmRankingRepository->save($ranking);

            $this->log('🗄️ [DATABASE SAVED] Persisted customer rankings', [
                'customer_id' => $customerId,
                'product_id' => $productId,
                'type' => $type,
                'ranking_count' => count($rankedProductIds),
                'expires_at' => $expiresAt,
                'model_used' => $ranking->getModelUsed(),
                'estimated_cost' => $ranking->getEstimatedCost(),
                'note' => 'Future visits will use database (no API cost!)'
            ]);
        } catch (\Exception $e) {
            $this->log('❌ Failed to save to database: ' . $e->getMessage());
        }
    }

    // ALSO save to CACHE (benefits both guests and logged-in users)
    if ($this->config->isCacheEnabled()) {
        $cacheData = $this->dehydrateResults($results);
        $this->cache->save(
            $this->serializer->serialize($cacheData),
            $cacheKey,
            [RecommendationCache::CACHE_TAG],
            $this->config->getCacheLifetime()
        );
        $this->log('💾 [CACHE SAVED] Stored in cache', [
            'product_id' => $productId,
            'type' => $type,
            'cache_key' => $cacheKey,
            'result_count' => count($results),
            'cache_lifetime' => $this->config->getCacheLifetime() . ' seconds',
            'note' => $customerId ? 'Cache serves as fallback for database' : 'Cache serves guest users'
        ]);
    }
}
```

## 📋 Installation Commands

```bash
# 1. Run database migration
php bin/magento setup:upgrade

# 2. Recompile dependency injection
php bin/magento setup:di:compile

# 3. Clear all caches
php bin/magento cache:flush

# 4. Verify table was created
php bin/magento setup:db:status
```

## 🧪 Testing

### Verify Database Table
```sql
DESCRIBE product_recommendation_llm_ranking;
```

Expected output:
```
+---------------------+--------------+------+-----+---------------------+
| Field               | Type         | Null | Key | Default             |
+---------------------+--------------+------+-----+---------------------+
| id                  | int unsigned | NO   | PRI | NULL                |
| customer_id         | int unsigned | YES  | MUL | NULL                |
| product_id          | int          | NO   | MUL | NULL                |
| recommendation_type | varchar(50)  | NO   |     | NULL                |
| store_id            | int unsigned | NO   |     | 0                   |
| ranked_product_ids  | text         | NO   |     | NULL                |
| ranking_metadata    | text         | YES  |     | NULL                |
| created_at          | timestamp    | NO   |     | CURRENT_TIMESTAMP   |
| expires_at          | timestamp    | NO   | MUL | NULL                |
| model_used          | varchar(100) | YES  |     | NULL                |
| estimated_cost      | decimal(10,6)| YES  |     | NULL                |
+---------------------+--------------+------+-----+---------------------+
```

### Test as Guest User
1. Clear all caches
2. Visit a product page as guest
3. Check logs:
```bash
tail -f var/log/product_recommendation.log | grep -E "(DATABASE|CACHE)"
```

Expected: `🔍 [CACHE MISS]` → Generate → `💾 [CACHE SAVED]`

### Test as Logged-In Customer
1. Log in to frontend
2. Visit a product page
3. Check logs:
```bash
tail -f var/log/product_recommendation.log | grep -E "(DATABASE|CACHE)"
```

First visit: `🔍 [DATABASE MISS]` → `🔍 [CACHE MISS]` → Generate → `🗄️ [DATABASE SAVED]` + `💾 [CACHE SAVED]`

Second visit: `🗄️ [DATABASE HIT]` (no API call!)

### Verify Database Storage
```sql
SELECT
    id,
    customer_id,
    product_id,
    recommendation_type,
    JSON_LENGTH(ranked_product_ids) as ranked_count,
    model_used,
    ROUND(estimated_cost, 6) as cost,
    created_at,
    expires_at
FROM product_recommendation_llm_ranking
ORDER BY created_at DESC
LIMIT 10;
```

### Check Saved Product IDs
```sql
SELECT
    customer_id,
    product_id,
    recommendation_type,
    ranked_product_ids
FROM product_recommendation_llm_ranking
WHERE customer_id = 1
LIMIT 1\G
```

Expected output shows JSON array like: `[25, 17, 42, 8, 13]`

## 💰 Cost Savings Analysis

### Before (Cache Only)
- Guest Visit 1: $0.018 (LLM API call)
- Guest Visit 2-N: $0 (cache hit)
- Customer Visit 1: $0.018 (LLM API call)
- **Customer Visit 2 after cache flush**: $0.018 (NEW LLM API call) ❌

### After (Database + Cache)
- Guest Visit 1: $0.018 (LLM API call)
- Guest Visit 2-N: $0 (cache hit)
- Customer Visit 1: $0.018 (LLM API call)
- **Customer Visit 2 after cache flush**: $0 (database hit) ✅

**Savings**: For 1000 logged-in customers viewing 10 products each:
- Without database: ~$180 (cache flushes require regeneration)
- With database: ~$18 (only first view per customer)
- **Total Savings**: $162 (90% reduction)

## 🔧 Future Enhancements

### 1. Add Cron Job to Clean Expired Records
```xml
<!-- etc/crontab.xml -->
<job name="product_recommendation_clean_expired_llm_rankings" instance="NavinDBhudiya\ProductRecommendation\Cron\CleanExpiredLlmRankings" method="execute">
    <schedule>0 2 * * *</schedule>
</job>
```

```php
// Cron/CleanExpiredLlmRankings.php
public function execute()
{
    $deleted = $this->llmRankingRepository->deleteExpired();
    $this->logger->info("Cleaned {$deleted} expired LLM rankings");
}
```

### 2. Add Admin Command
```php
// Console/Command/CleanLlmRankings.php
php bin/magento recommendation:clean-llm-rankings --customer-id=123
php bin/magento recommendation:clean-llm-rankings --expired
php bin/magento recommendation:clean-llm-rankings --all
```

### 3. Add Admin Grid
Create admin interface at **Stores → AI Product Recommendations → LLM Rankings** to view/manage stored rankings.

## 📊 Benefits Summary

1. ✅ **Persistent Storage**: Survives cache flushes
2. ✅ **Personalized**: Each customer has unique rankings
3. ✅ **Cost Efficient**: 90% reduction in API calls for registered users
4. ✅ **Performance**: Database faster than API, cache fastest
5. ✅ **Scalability**: Cache for guests, database for customers
6. ✅ **Reliability**: Multi-layer fallback (DB → Cache → Generate)

## 🎯 Current Status

- ✅ Database schema created
- ✅ Models and Repository implemented
- ✅ DI configuration updated
- ✅ Read logic (database check) implemented
- 🟡 **Write logic (database save) - Ready to implement** (code provided above)
- ⏳ Testing pending

Once you apply the database save logic change above and run `php bin/magento setup:upgrade && php bin/magento setup:di:compile`, the hybrid storage will be fully functional!