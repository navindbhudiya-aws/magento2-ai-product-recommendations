<?php
/**
 * NavinDBhudiya ProductRecommendation
 *
 * @category  NavinDBhudiya
 * @package   NavinDBhudiya_ProductRecommendation
 * @author    Navin Bhudiya
 * @license   MIT License
 */

declare(strict_types=1);

namespace NavinDBhudiya\ProductRecommendation\Plugin\Checkout;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Checkout\Block\Cart\Crosssell;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Store\Model\StoreManagerInterface;
use NavinDBhudiya\ProductRecommendation\Api\RecommendationServiceInterface;
use NavinDBhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Plugin to override crosssell products with AI recommendations
 */
class CrosssellProducts
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
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

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
     * @param CheckoutSession $checkoutSession
     * @param LoggerInterface $logger
     * @param CollectionFactory $productCollectionFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        RecommendationServiceInterface $recommendationService,
        Config $config,
        CheckoutSession $checkoutSession,
        LoggerInterface $logger,
        CollectionFactory $productCollectionFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->recommendationService = $recommendationService;
        $this->config = $config;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * After get items - replace with AI recommendations
     *
     * @param Crosssell $subject
     * @param mixed $result Original result (should be array)
     * @return array
     */
    public function afterGetItems(Crosssell $subject, $result): array
    {
        // Ensure result is array
        $originalItems = is_array($result) ? $result : [];

        // Prevent infinite recursion
        if (self::$isProcessing) {
            return $originalItems;
        }

        // Check if module is enabled
        if (!$this->config->isEnabled() || !$this->config->isCrossSellEnabled()) {
            return $originalItems;
        }

        try {
            self::$isProcessing = true;

            // Get products from cart
            $quote = $this->checkoutSession->getQuote();

            if (!$quote || !$quote->getId()) {
                self::$isProcessing = false;
                return $originalItems;
            }

            $cartItems = $quote->getAllVisibleItems();

            if (empty($cartItems)) {
                self::$isProcessing = false;
                return $originalItems;
            }

            // Get cart product IDs to filter out
            $cartProductIds = [];
            foreach ($cartItems as $item) {
                $cartProductIds[] = (int) $item->getProduct()->getId();
            }

            // Get AI recommendations for the last added item
            $lastItem = end($cartItems);
            /** @var Product|null $product */
            $product = $lastItem->getProduct();

            if (!$product || !$product->getId()) {
                self::$isProcessing = false;
                return $originalItems;
            }

            // Get AI recommendations
            $aiProducts = $this->recommendationService->getCrossSellProducts($product);

            if (empty($aiProducts)) {
                self::$isProcessing = false;
                // Fallback to native if configured
                if ($this->config->isFallbackToNativeEnabled()) {
                    return $originalItems;
                }
                return [];
            }

            // Extract product IDs from AI recommendations (excluding ALL cart items)
            $productIds = [];
            foreach ($aiProducts as $aiProduct) {
                $aiProductId = (int) $aiProduct->getId();
                // Ensure cart products are never included
                if (!in_array($aiProductId, $cartProductIds, true)) {
                    $productIds[] = $aiProductId;
                }
            }

            if (empty($productIds)) {
                self::$isProcessing = false;
                if ($this->config->isFallbackToNativeEnabled()) {
                    return $originalItems;
                }
                return [];
            }

            // Create collection and get items as array
            $aiCollection = $this->createProductCollection($productIds);
            $items = $aiCollection->getItems();

            self::$isProcessing = false;
            return $items;

        } catch (\Exception $e) {
            $this->logger->error(
                '[ProductRecommendation] CrosssellProducts plugin error: ' . $e->getMessage()
            );
            self::$isProcessing = false;
            return $originalItems;
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
}
