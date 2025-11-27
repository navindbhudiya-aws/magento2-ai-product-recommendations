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

namespace Navindbhudiya\ProductRecommendation\Plugin\Product;

use Magento\Catalog\Block\Product\ProductList\Upsell;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Navindbhudiya\ProductRecommendation\Api\RecommendationServiceInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Plugin to override upsell products with AI recommendations
 */
class UpsellProducts
{
    /**
     * Static flag to prevent recursion
     *
     * @var bool
     */
    private static bool $isProcessing = false;

    /**
     * @var RecommendationServiceInterface
     */
    private RecommendationServiceInterface $recommendationService;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Registry
     */
    private Registry $registry;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $productCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param RecommendationServiceInterface $recommendationService
     * @param Config $config
     * @param Registry $registry
     * @param LoggerInterface $logger
     * @param CollectionFactory $productCollectionFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        RecommendationServiceInterface $recommendationService,
        Config $config,
        Registry $registry,
        LoggerInterface $logger,
        CollectionFactory $productCollectionFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->recommendationService = $recommendationService;
        $this->config = $config;
        $this->registry = $registry;
        $this->logger = $logger;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * After get item collection - replace with AI recommendations collection
     *
     * @param Upsell $subject
     * @param mixed $result Original result (should be Collection)
     * @return Collection
     */
    public function afterGetItemCollection(Upsell $subject, $result): Collection
    {
        // Prevent infinite recursion
        if (self::$isProcessing) {
            if ($result instanceof Collection) {
                return $result;
            }
            return $this->createEmptyCollection();
        }

        // Check if module is enabled
        if (!$this->config->isEnabled() || !$this->config->isUpSellEnabled()) {
            if ($result instanceof Collection) {
                return $result;
            }
            return $this->createEmptyCollection();
        }

        try {
            self::$isProcessing = true;

            /** @var Product|null $product */
            $product = $this->registry->registry('current_product');

            if (!$product || !$product->getId()) {
                self::$isProcessing = false;
                if ($result instanceof Collection) {
                    return $result;
                }
                return $this->createEmptyCollection();
            }

            // Get AI recommendations
            $aiProducts = $this->recommendationService->getUpSellProducts($product);

            if (empty($aiProducts)) {
                self::$isProcessing = false;
                // Fallback to native if configured
                if ($this->config->isFallbackToNativeEnabled()) {
                    if ($result instanceof Collection) {
                        return $result;
                    }
                }
                return $this->createEmptyCollection();
            }

            // Extract product IDs from AI recommendations
            $productIds = [];
            foreach ($aiProducts as $aiProduct) {
                $productIds[] = (int) $aiProduct->getId();
            }

            // Create new collection with AI product IDs
            $aiCollection = $this->createProductCollection($productIds);

            self::$isProcessing = false;
            return $aiCollection;

        } catch (\Exception $e) {
            $this->logger->error(
                '[ProductRecommendation] UpsellProducts plugin error: ' . $e->getMessage()
            );
            self::$isProcessing = false;
            if ($result instanceof Collection) {
                return $result;
            }
            return $this->createEmptyCollection();
        }
    }

    /**
     * Create product collection with specific IDs
     *
     * @param array $productIds
     * @return Collection
     */
    private function createProductCollection(array $productIds): Collection
    {
        /** @var Collection $collection */
        $collection = $this->productCollectionFactory->create();

        $collection->addAttributeToSelect([
            'name',
            'sku',
            'price',
            'special_price',
            'special_from_date',
            'special_to_date',
            'small_image',
            'thumbnail',
            'url_key',
            'short_description'
        ]);

        $collection->addIdFilter($productIds);
        $collection->addStoreFilter($this->storeManager->getStore()->getId());
        $collection->addAttributeToFilter(
            'status',
            \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED
        );
        $collection->addAttributeToFilter(
            'visibility',
            ['in' => [
                \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_CATALOG,
                \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH
            ]]
        );

        // Add price data
        $collection->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents();

        // Maintain original order from AI recommendations
        if (!empty($productIds)) {
            $collection->getSelect()->order(
                new \Zend_Db_Expr('FIELD(e.entity_id, ' . implode(',', $productIds) . ')')
            );
        }

        return $collection;
    }

    /**
     * Create empty collection
     *
     * @return Collection
     */
    private function createEmptyCollection(): Collection
    {
        /** @var Collection $collection */
        $collection = $this->productCollectionFactory->create();
        $collection->addIdFilter([0]); // Filter that matches nothing
        return $collection;
    }
}
