# Hybrid Storage Implementation for LLM Rankings

## Overview
This implementation provides hybrid storage for LLM re-ranked product recommendations:
- **Database**: For logged-in customers (personalized, persistent)
- **Cache**: For guest users (temporary, fast)

## Implementation Status

### ✅ Completed
1. Database schema (`etc/db_schema.xml`) - Table `product_recommendation_llm_ranking`
2. Data Model (`Model/LlmRanking.php`) with JSON encoding for product IDs
3. ResourceModel and Collection (`Model/ResourceModel/LlmRanking.php`)
4. Repository interface and implementation (`Api/LlmRankingRepositoryInterface.php`, `Model/LlmRankingRepository.php`)
5. Dependency injection configuration (`etc/di.xml`)
6. Constructor injection in `RecommendationService.php` (lines 124-198)

### ⏳ Next Step
Modify `getRecommendationsWithScores` method in `RecommendationService.php` (lines 336-437)

## Required Changes to RecommendationService.php

Replace lines 353-371 (cache check logic) and lines 413-430 (cache save logic) with the following implementation:

```php
// Get customer ID if logged in
$customerId = null;
if ($this->customerSession && $this->customerSession->isLoggedIn()) {
    $customerId = (int) $this->customerSession->getCustomerId();
}

// LAYER 1: Check Database (for logged-in customers only)
if ($customerId && $this->llmRankingRepository) {
    try {
        $dbRanking = $this->llmRankingRepository->getByProductAndCustomer(
            $productId,
            $type,
            $customerId,
            $storeId
        );

        if ($dbRanking && !$dbRanking->isExpired()) {
            $this->log('🗄️ [DATABASE HIT] Returning stored LLM rankings', [
                'product_id' => $productId,
                'customer_id' => $customerId,
                'type' => $type,
                'created_at' => $dbRanking->getCreatedAt(),
                'expires_at' => $dbRanking->getExpiresAt(),
                'cost_saved' => '$0.018 (no API call)'
            ]);

            $rankedIds = $dbRanking->getRankedProductIds();
            return $this->hydrateFromProductIds($rankedIds, $type, $limit);
        }

        $this->log('🔍 [DATABASE MISS] No valid ranking in database for customer', [
            'customer_id' => $customerId,
            'product_id' => $productId
        ]);
    } catch (\Exception $e) {
        $this->log('❌ Database check error: ' . $e->getMessage());
    }
}

// LAYER 2: Check Cache
$cacheKey = $this->getCacheKey($productId, $type, $storeId);
if ($this->config->isCacheEnabled()) {
    $cached = $this->cache->load($cacheKey);
    if ($cached) {
        $this->log('💾 [CACHE HIT] Returning cached rankings', [
            'product_id' => $productId,
            'type' => $type,
            'cache_key' => $cacheKey,
            'cost_saved' => '$0.018 (no API call)'
        ]);
        $cachedData = $this->serializer->unserialize($cached);
        return $this->hydrateResults($cachedData, $type, $limit);
    }
    $this->log('🔍 [CACHE MISS] Will generate new recommendations', [
        'product_id' => $productId,
        'type' => $type
    ]);
}
```

After generating results (after line 411 `$results = $this->processResults(...)`), replace lines 413-430 with:

```php
// SAVE RESULTS
if (!empty($results)) {
    $rankedProductIds = array_map(fn($r) => (int)$r->getProduct()->getId(), $results);

    // Save to DATABASE for logged-in customers
    if ($customerId && $this->llmRankingRepository && $this->llmRankingFactory && $this->dateTime) {
        try {
            $expiresAt = gmdate('Y-m-d H:i:s', $this->dateTime->gmtTimestamp() + $this->config->getCacheLifetime());

            /** @var \Navindbhudiya\ProductRecommendation\Api\Data\LlmRankingInterface $ranking */
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

## New Helper Method

Add this method to RecommendationService.php before the log() method:

```php
/**
 * Hydrate results from product IDs (database storage)
 *
 * @param array $productIds
 * @param string $type
 * @param int $limit
 * @return RecommendationResultInterface[]
 */
private function hydrateFromProductIds(array $productIds, string $type, int $limit): array
{
    if (empty($productIds)) {
        return [];
    }

    // Limit to requested count
    $productIds = array_slice($productIds, 0, $limit);

    // Convert to cache format for reuse
    $cachedData = [];
    foreach ($productIds as $productId) {
        $cachedData[] = [
            'product_id' => $productId,
            'score' => 0.0, // Score not stored in DB, only ranking order
            'distance' => 0.0,
            'type' => $type,
            'metadata' => []
        ];
    }

    return $this->hydrateResults($cachedData, $type, $limit);
}
```

## Installation Steps

1. Run database migration:
```bash
php bin/magento setup:upgrade
```

2. Recompile dependency injection:
```bash
php bin/magento setup:di:compile
```

3. Clear caches:
```bash
php bin/magento cache:flush
```

## Testing Verification

### Test as Guest User
```bash
# Visit product page as guest
# Check logs for: 💾 [CACHE MISS] → Generate → 💾 [CACHE SAVED]
tail -f var/log/product_recommendation.log | grep -E "(CACHE|DATABASE)"
```

### Test as Logged-In Customer
```bash
# Visit product page as logged-in customer
# Check logs for: 🔍 [DATABASE MISS] → Generate → 🗄️ [DATABASE SAVED] + 💾 [CACHE SAVED]
# Second visit should show: 🗄️ [DATABASE HIT]
tail -f var/log/product_recommendation.log | grep -E "(CACHE|DATABASE)"
```

### Verify Database Storage
```sql
SELECT
    id,
    customer_id,
    product_id,
    recommendation_type,
    model_used,
    estimated_cost,
    created_at,
    expires_at
FROM product_recommendation_llm_ranking
ORDER BY created_at DESC
LIMIT 10;
```

## Benefits

1. **Cost Savings**: LLM API calls only on first request per customer
2. **Performance**: Database is faster than API calls, cache is fastest
3. **Personalization**: Each logged-in customer gets persistent rankings
4. **Scalability**: Cache handles anonymous traffic, DB handles registered users
5. **Reliability**: Database survives cache flushes

## Storage Layers

```
Request Flow:
┌─────────────────────┐
│ Is customer logged │
│      in?           │
└──────┬──────────────┘
       │
       ├─ YES ─────► Check Database ──► Found & Valid? ──► Return
       │                    │                                  ▲
       │                    └─ No/Expired ────────────────────┤
       │                                                       │
       └─ NO ──────► Check Cache ──────► Found? ────► Return ─┤
                            │                                  │
                            └─ Miss ─────► Generate Rankings ─┤
                                                 │             │
                                                 ▼             │
                                        Save to DB (if logged in)
                                        Save to Cache (always)
                                                 │             │
                                                 └─────────────┘
```

## Configuration

Current TTL: 3600 seconds (1 hour) - configurable via:
```php
$this->config->getCacheLifetime()
```

## Future Enhancements

1. Add cron job to clean expired database records:
   `$this->llmRankingRepository->deleteExpired()`

2. Add admin command to view/clear customer rankings

3. Add config option to control database storage enable/disable

4. Track cost savings in admin dashboard