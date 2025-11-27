<?php
/**
 * Navindbhudiya ProductRecommendation
 *
 * @category  Navindbhudiya
 * @package   Navindbhudiya_ProductRecommendation
 * @author    Navin Bhudiya
 * @license   MIT License
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Service;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Navindbhudiya\ProductRecommendation\Api\Data\RecommendationResultInterface;
use Navindbhudiya\ProductRecommendation\Api\Data\RecommendationResultInterfaceFactory;
use Navindbhudiya\ProductRecommendation\Api\EmbeddingProviderInterface;
use Navindbhudiya\ProductRecommendation\Api\RecommendationServiceInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Navindbhudiya\ProductRecommendation\Model\Cache\Type\Recommendation as RecommendationCache;
use Psr\Log\LoggerInterface;

/**
 * Service for getting AI-powered product recommendations
 */
class RecommendationService implements RecommendationServiceInterface
{
    private const CACHE_PREFIX = 'ai_rec_';

    /**
     * Static flag to prevent recursion
     *
     * @var bool
     */
    private static bool $isProcessing = false;

    /**
     * In-memory cache to avoid repeated database calls
     *
     * @var array
     */
    private static array $memoryCache = [];

    /**
     * @var ChromaClient
     */
    private ChromaClient $chromaClient;

    /**
     * @var EmbeddingProviderInterface
     */
    private EmbeddingProviderInterface $embeddingProvider;

    /**
     * @var ProductTextBuilder
     */
    private ProductTextBuilder $textBuilder;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $productCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var RecommendationResultInterfaceFactory
     */
    private RecommendationResultInterfaceFactory $resultFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var StockHelper
     */
    private StockHelper $stockHelper;

    /**
     * @param ChromaClient $chromaClient
     * @param EmbeddingProviderInterface $embeddingProvider
     * @param ProductTextBuilder $textBuilder
     * @param ProductRepositoryInterface $productRepository
     * @param CollectionFactory $productCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param RecommendationResultInterfaceFactory $resultFactory
     * @param LoggerInterface $logger
     * @param StockHelper $stockHelper
     */
    public function __construct(
        ChromaClient $chromaClient,
        EmbeddingProviderInterface $embeddingProvider,
        ProductTextBuilder $textBuilder,
        ProductRepositoryInterface $productRepository,
        CollectionFactory $productCollectionFactory,
        StoreManagerInterface $storeManager,
        Config $config,
        CacheInterface $cache,
        SerializerInterface $serializer,
        RecommendationResultInterfaceFactory $resultFactory,
        LoggerInterface $logger,
        StockHelper $stockHelper
    ) {
        $this->chromaClient = $chromaClient;
        $this->embeddingProvider = $embeddingProvider;
        $this->textBuilder = $textBuilder;
        $this->productRepository = $productRepository;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->resultFactory = $resultFactory;
        $this->logger = $logger;
        $this->stockHelper = $stockHelper;
    }

    /**
     * @inheritDoc
     */
    public function getRelatedProducts($product, ?int $limit = null, ?int $storeId = null): array
    {
        // Prevent infinite recursion
        if (self::$isProcessing) {
            return [];
        }

        if (!$this->config->isEnabled($storeId) || !$this->config->isRelatedEnabled($storeId)) {
            return [];
        }

        try {
            self::$isProcessing = true;
            
            $limit = $limit ?? $this->config->getRelatedCount($storeId);
            $results = $this->getRecommendationsWithScores($product, self::TYPE_RELATED, $limit, $storeId);

            $products = array_map(fn($result) => $result->getProduct(), $results);
            
            self::$isProcessing = false;
            return $products;
        } catch (\Exception $e) {
            self::$isProcessing = false;
            $this->log('getRelatedProducts error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function getCrossSellProducts($product, ?int $limit = null, ?int $storeId = null): array
    {
        // Prevent infinite recursion
        if (self::$isProcessing) {
            return [];
        }

        if (!$this->config->isEnabled($storeId) || !$this->config->isCrossSellEnabled($storeId)) {
            return [];
        }

        try {
            self::$isProcessing = true;
            
            $limit = $limit ?? $this->config->getCrossSellCount($storeId);
            $results = $this->getRecommendationsWithScores($product, self::TYPE_CROSSSELL, $limit, $storeId);

            $products = array_map(fn($result) => $result->getProduct(), $results);
            
            self::$isProcessing = false;
            return $products;
        } catch (\Exception $e) {
            self::$isProcessing = false;
            $this->log('getCrossSellProducts error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function getUpSellProducts($product, ?int $limit = null, ?int $storeId = null): array
    {
        // Prevent infinite recursion
        if (self::$isProcessing) {
            return [];
        }

        if (!$this->config->isEnabled($storeId) || !$this->config->isUpSellEnabled($storeId)) {
            return [];
        }

        try {
            self::$isProcessing = true;
            
            $limit = $limit ?? $this->config->getUpSellCount($storeId);
            $results = $this->getRecommendationsWithScores($product, self::TYPE_UPSELL, $limit, $storeId);

            $products = array_map(fn($result) => $result->getProduct(), $results);
            
            self::$isProcessing = false;
            return $products;
        } catch (\Exception $e) {
            self::$isProcessing = false;
            $this->log('getUpSellProducts error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function getSimilarProductsByQuery(string $query, int $limit = 10, ?int $storeId = null): array
    {
        if (!$this->config->isEnabled($storeId)) {
            return [];
        }

        try {
            $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
            
            // Generate embedding for query
            $queryEmbedding = $this->embeddingProvider->generateEmbedding($query);
            
            if (empty($queryEmbedding)) {
                $this->log('Failed to generate embedding for query');
                return [];
            }
            
            $collectionName = $this->config->getCollectionName();
            $collectionId = $this->chromaClient->getCollectionId($collectionName);

            // Query ChromaDB with embeddings
            $queryResult = $this->chromaClient->query(
                $collectionId,
                [], // No query_texts
                $limit + 5,
                [], // where
                [], // whereDocument
                [$queryEmbedding] // query_embeddings
            );

            return $this->processQueryResults($queryResult, $limit, $storeId);
        } catch (\Exception $e) {
            $this->log('Query by text failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function getRecommendationsWithScores(
        $product,
        string $type = self::TYPE_RELATED,
        ?int $limit = null,
        ?int $storeId = null
    ): array {
        try {
            $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
            $product = $this->resolveProduct($product, $storeId);

            if (!$product) {
                return [];
            }

            $productId = (int) $product->getId();
            $limit = $limit ?? $this->getDefaultLimit($type, $storeId);

            // Check cache
            $cacheKey = $this->getCacheKey($productId, $type, $storeId);
            if ($this->config->isCacheEnabled()) {
                $cached = $this->cache->load($cacheKey);
                if ($cached) {
                    $cachedData = $this->serializer->unserialize($cached);
                    return $this->hydrateResults($cachedData, $type, $limit);
                }
            }

            // Build query text from product
            $queryText = $this->textBuilder->buildText($product, $storeId);

            if (empty($queryText)) {
                $this->log('Empty query text for product ' . $productId);
                return [];
            }

            // Generate embedding for query
            $this->log('Generating embedding for product ' . $productId);
            $queryEmbedding = $this->embeddingProvider->generateEmbedding($queryText);

            if (empty($queryEmbedding)) {
                $this->log('Failed to generate embedding for product ' . $productId . '. Check embedding service!');
                return [];
            }

            $this->log('Embedding generated successfully, dimension: ' . count($queryEmbedding));

            // Get collection
            $collectionName = $this->config->getCollectionName();
            $collectionId = $this->chromaClient->getCollectionId($collectionName);

            // Build filter
            $where = $this->buildWhereFilter($product, $type, $storeId);

            // Query ChromaDB with embeddings (NOT query_texts!)
            $nResults = $limit + 10; // Get extra to account for filtering
            $queryResult = $this->chromaClient->query(
                $collectionId,
                [], // No query_texts - we use embeddings
                $nResults,
                $where,
                [], // whereDocument
                [$queryEmbedding] // query_embeddings
            );

            // Process results
            $results = $this->processResults($queryResult, $product, $type, $limit, $storeId);

            // Cache results
            if ($this->config->isCacheEnabled() && !empty($results)) {
                $cacheData = $this->dehydrateResults($results);
                $this->cache->save(
                    $this->serializer->serialize($cacheData),
                    $cacheKey,
                    [RecommendationCache::CACHE_TAG],
                    $this->config->getCacheLifetime()
                );
            }

            return $results;
        } catch (\Exception $e) {
            $this->log('Get recommendations failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function hasAiRecommendations(int $productId): bool
    {
        try {
            $collectionName = $this->config->getCollectionName();
            $collectionId = $this->chromaClient->getCollectionId($collectionName);

            $documents = $this->chromaClient->getDocuments($collectionId, ['product_' . $productId]);

            return !empty($documents['ids'] ?? []);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function clearCache(int $productId): void
    {
        foreach ([self::TYPE_RELATED, self::TYPE_CROSSSELL, self::TYPE_UPSELL] as $type) {
            $pattern = self::CACHE_PREFIX . $productId . '_' . $type . '_*';
            // Clear all store variations
            for ($storeId = 0; $storeId <= 100; $storeId++) {
                $cacheKey = $this->getCacheKey($productId, $type, $storeId);
                $this->cache->remove($cacheKey);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function clearAllCache(): void
    {
        $this->cache->clean([RecommendationCache::CACHE_TAG]);
    }

    /**
     * Resolve product from ID or instance
     *
     * @param ProductInterface|int $product
     * @param int $storeId
     * @return ProductInterface|null
     */
    private function resolveProduct($product, int $storeId): ?ProductInterface
    {
        if ($product instanceof ProductInterface) {
            return $product;
        }

        try {
            return $this->productRepository->getById((int) $product, false, $storeId);
        } catch (\Exception $e) {
            $this->log('Failed to load product: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get default limit for type
     *
     * @param string $type
     * @param int|null $storeId
     * @return int
     */
    private function getDefaultLimit(string $type, ?int $storeId): int
    {
        return match ($type) {
            self::TYPE_CROSSSELL => $this->config->getCrossSellCount($storeId),
            self::TYPE_UPSELL => $this->config->getUpSellCount($storeId),
            default => $this->config->getRelatedCount($storeId),
        };
    }

    /**
     * Build where filter for ChromaDB query
     *
     * @param ProductInterface $product
     * @param string $type
     * @param int $storeId
     * @return array
     */
    private function buildWhereFilter(ProductInterface $product, string $type, int $storeId): array
    {
        $conditions = [];

        // Exclude current product
        $conditions[] = ['product_id' => ['$ne' => (int) $product->getId()]];

        // Add store filter
        $conditions[] = ['store_id' => $storeId];

        // ChromaDB requires $and operator for multiple conditions
        if (count($conditions) > 1) {
            return ['$and' => $conditions];
        }

        // Single condition doesn't need $and
        return $conditions[0] ?? [];
    }

    /**
     * Process ChromaDB query results
     *
     * @param array $queryResult
     * @param ProductInterface $sourceProduct
     * @param string $type
     * @param int $limit
     * @param int $storeId
     * @return RecommendationResultInterface[]
     */
    private function processResults(
        array $queryResult,
        ProductInterface $sourceProduct,
        string $type,
        int $limit,
        int $storeId
    ): array {
        $results = [];

        if (empty($queryResult['ids'][0] ?? [])) {
            return $results;
        }

        $ids = $queryResult['ids'][0];
        $distances = $queryResult['distances'][0] ?? [];
        $metadatas = $queryResult['metadatas'][0] ?? [];

        $productIds = [];
        $idDistanceMap = [];

        foreach ($ids as $index => $id) {
            // Extract product ID from document ID (format: product_{id})
            $productId = $this->extractProductId($id);
            if ($productId && $productId !== (int) $sourceProduct->getId()) {
                $productIds[] = $productId;
                $idDistanceMap[$productId] = $distances[$index] ?? 0;
            }
        }

        if (empty($productIds)) {
            return $results;
        }

        // Load products
        $products = $this->loadProducts($productIds, $storeId, $type, $sourceProduct);

        // Build results with scores
        $threshold = $this->config->getSimilarityThreshold($storeId);

        foreach ($products as $product) {
            $productId = (int) $product->getId();
            $distance = $idDistanceMap[$productId] ?? 1;
            $score = $this->distanceToScore($distance);

            if ($score < $threshold) {
                continue;
            }

            /** @var RecommendationResultInterface $result */
            $result = $this->resultFactory->create();
            $result->setProduct($product)
                ->setScore($score)
                ->setDistance($distance)
                ->setType($type)
                ->setMetadata([
                    'source_product_id' => $sourceProduct->getId(),
                    'store_id' => $storeId,
                ]);

            $results[] = $result;

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * Process query results for text-based search
     *
     * @param array $queryResult
     * @param int $limit
     * @param int $storeId
     * @return ProductInterface[]
     */
    private function processQueryResults(array $queryResult, int $limit, int $storeId): array
    {
        if (empty($queryResult['ids'][0] ?? [])) {
            return [];
        }

        $productIds = [];
        foreach ($queryResult['ids'][0] as $id) {
            $productId = $this->extractProductId($id);
            if ($productId) {
                $productIds[] = $productId;
            }
        }

        if (empty($productIds)) {
            return [];
        }

        return $this->loadProducts($productIds, $storeId);
    }

    /**
     * Load products with filters
     *
     * @param array $productIds
     * @param int $storeId
     * @param string|null $type
     * @param ProductInterface|null $sourceProduct
     * @return ProductInterface[]
     */
    private function loadProducts(
        array $productIds,
        int $storeId,
        ?string $type = null,
        ?ProductInterface $sourceProduct = null
    ): array {
        /** @var Collection $collection */
        $collection = $this->productCollectionFactory->create();
        $collection->addIdFilter($productIds)
            ->addStoreFilter($storeId)
            ->addAttributeToSelect(['name', 'price', 'small_image', 'url_key'])
            ->addFieldToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->addFieldToFilter('visibility', ['in' => [
                Visibility::VISIBILITY_IN_CATALOG,
                Visibility::VISIBILITY_BOTH,
            ]]);

        // Add stock filter
        $this->stockHelper->addInStockFilterToCollection($collection);

        // Apply type-specific filters
        if ($type && $sourceProduct) {
            $this->applyTypeFilters($collection, $type, $sourceProduct, $storeId);
        }

        // Maintain original order
        $collection->getSelect()->order(
            new \Zend_Db_Expr('FIELD(e.entity_id, ' . implode(',', $productIds) . ')')
        );

        return $collection->getItems();
    }

    /**
     * Apply type-specific filters to collection
     *
     * @param Collection $collection
     * @param string $type
     * @param ProductInterface $sourceProduct
     * @param int $storeId
     * @return void
     */
    private function applyTypeFilters(
        Collection $collection,
        string $type,
        ProductInterface $sourceProduct,
        int $storeId
    ): void {
        switch ($type) {
            case self::TYPE_CROSSSELL:
                if ($this->config->excludeSameCategoryForCrossSell($storeId)) {
                    // Exclude products from same categories
                    if ($sourceProduct instanceof Product) {
                        $categoryIds = $sourceProduct->getCategoryIds();
                        if (!empty($categoryIds)) {
                            // This is a simplified approach - for better performance,
                            // you might want to use a more sophisticated category filter
                        }
                    }
                }
                break;

            case self::TYPE_UPSELL:
                $threshold = $this->config->getUpSellPriceThreshold($storeId);
                if ($threshold > 0 && $sourceProduct->getPrice()) {
                    $minPrice = $sourceProduct->getPrice() * (1 + $threshold / 100);
                    $collection->addFieldToFilter('price', ['gteq' => $minPrice]);
                }
                break;
        }
    }

    /**
     * Extract product ID from ChromaDB document ID
     *
     * @param string $documentId
     * @return int|null
     */
    private function extractProductId(string $documentId): ?int
    {
        if (preg_match('/^product_(\d+)(?:_\d+)?$/', $documentId, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    /**
     * Convert distance to similarity score
     *
     * @param float $distance
     * @return float Score between 0 and 1
     */
    private function distanceToScore(float $distance): float
    {
        // ChromaDB uses L2 distance by default
        // Convert to similarity score: score = 1 / (1 + distance)
        return 1 / (1 + $distance);
    }

    /**
     * Get cache key
     *
     * @param int $productId
     * @param string $type
     * @param int $storeId
     * @return string
     */
    private function getCacheKey(int $productId, string $type, int $storeId): string
    {
        return self::CACHE_PREFIX . $productId . '_' . $type . '_' . $storeId;
    }

    /**
     * Dehydrate results for caching
     *
     * @param RecommendationResultInterface[] $results
     * @return array
     */
    private function dehydrateResults(array $results): array
    {
        $data = [];
        foreach ($results as $result) {
            $data[] = [
                'product_id' => $result->getProduct()->getId(),
                'score' => $result->getScore(),
                'distance' => $result->getDistance(),
                'type' => $result->getType(),
                'metadata' => $result->getMetadata(),
            ];
        }
        return $data;
    }

    /**
     * Hydrate results from cache
     *
     * @param array $cachedData
     * @param string $type
     * @param int $limit
     * @return RecommendationResultInterface[]
     */
    private function hydrateResults(array $cachedData, string $type, int $limit): array
    {
        if (empty($cachedData)) {
            return [];
        }

        // Collect product IDs
        $productIds = [];
        $dataByProductId = [];
        
        foreach ($cachedData as $item) {
            if (count($productIds) >= $limit) {
                break;
            }
            $productId = (int) $item['product_id'];
            $productIds[] = $productId;
            $dataByProductId[$productId] = $item;
        }

        if (empty($productIds)) {
            return [];
        }

        // Load all products in one query using collection
        try {
            $collection = $this->productCollectionFactory->create();
            $collection->addIdFilter($productIds)
                ->addAttributeToSelect(['name', 'sku', 'price', 'small_image', 'url_key'])
                ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
            
            // Maintain order
            $collection->getSelect()->order(
                new \Zend_Db_Expr('FIELD(e.entity_id, ' . implode(',', $productIds) . ')')
            );

            $results = [];
            foreach ($collection->getItems() as $product) {
                $productId = (int) $product->getId();
                if (!isset($dataByProductId[$productId])) {
                    continue;
                }
                
                $item = $dataByProductId[$productId];
                
                /** @var RecommendationResultInterface $result */
                $result = $this->resultFactory->create();
                $result->setProduct($product)
                    ->setScore((float) ($item['score'] ?? 0))
                    ->setDistance((float) ($item['distance'] ?? 0))
                    ->setType($type)
                    ->setMetadata($item['metadata'] ?? []);

                $results[] = $result;
            }

            return $results;
        } catch (\Exception $e) {
            $this->log('hydrateResults error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Log message if debug mode is enabled
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    private function log(string $message, array $context = []): void
    {
        if ($this->config->isDebugMode()) {
            $this->logger->debug('[ProductRecommendation] ' . $message, $context);
        }
    }
}
