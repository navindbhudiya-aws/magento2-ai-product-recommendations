<?php
/**
 * NavinDBhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace NavinDBhudiya\ProductRecommendation\Block\Personalized;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Pricing\Price\SpecialPriceBulkResolverInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Data\Helper\PostHelper;
use Magento\Framework\Url\EncoderInterface as UrlEncoder;
use NavinDBhudiya\ProductRecommendation\Api\PersonalizedRecommendationInterface;
use NavinDBhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Block for displaying personalized recommendations
 */
class Recommendations extends AbstractProduct
{
    /**
     * @var PersonalizedRecommendationInterface
     */
    private PersonalizedRecommendationInterface $recommendationService;

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var PostHelper
     */
    private PostHelper $postHelper;

    /**
     * @var UrlEncoder
     */
    private UrlEncoder $urlEncoder;

    /**
     * @var string
     */
    private string $recommendationType = '';

    /**
     * @var int
     */
    private int $productLimit = 8;

    /**
     * @var array|null
     */
    private ?array $loadedProducts = null;

    /**
     * @var SpecialPriceBulkResolverInterface
     */
    private SpecialPriceBulkResolverInterface $specialPriceBulkResolver;

    /**
     * @var array|null
     */
    private ?array $specialPriceMap = null;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $productCollectionFactory;

    /**
     * @var ProductCollection|null
     */
    private ?ProductCollection $productCollection = null;

    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Context $context
     * @param PersonalizedRecommendationInterface $recommendationService
     * @param CustomerSession $customerSession
     * @param Config $config
     * @param PostHelper $postHelper
     * @param UrlEncoder $urlEncoder
     * @param SpecialPriceBulkResolverInterface $specialPriceBulkResolver
     * @param CollectionFactory $productCollectionFactory
     * @param CheckoutSession $checkoutSession
     * @param LoggerInterface $logger
     * @param array $data
     */
    public function __construct(
        Context $context,
        PersonalizedRecommendationInterface $recommendationService,
        CustomerSession $customerSession,
        Config $config,
        PostHelper $postHelper,
        UrlEncoder $urlEncoder,
        SpecialPriceBulkResolverInterface $specialPriceBulkResolver,
        CollectionFactory $productCollectionFactory,
        CheckoutSession $checkoutSession,
        LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->recommendationService = $recommendationService;
        $this->customerSession = $customerSession;
        $this->config = $config;
        $this->postHelper = $postHelper;
        $this->urlEncoder = $urlEncoder;
        $this->specialPriceBulkResolver = $specialPriceBulkResolver;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
    }

    /**
     * Set recommendation type
     *
     * @param string $type
     * @return $this
     */
    public function setRecommendationType(string $type): self
    {
        $this->recommendationType = $type;
        $this->loadedProducts = null; // Reset cache
        return $this;
    }

    /**
     * Get recommendation type
     *
     * @return string
     */
    public function getRecommendationType(): string
    {
        return $this->recommendationType ?: $this->getData('recommendation_type') ?: '';
    }

    /**
     * Set product limit
     *
     * @param int $limit
     * @return $this
     */
    public function setProductLimit(int $limit): self
    {
        $this->productLimit = $limit;
        $this->loadedProducts = null;
        return $this;
    }

    /**
     * Get product limit
     *
     * @return int
     */
    public function getProductLimit(): int
    {
        return (int) ($this->getData('product_limit') ?: $this->productLimit);
    }

    /**
     * Get block title based on type
     *
     * @return string
     */
    public function getTitle(): string
    {
        $customTitle = $this->getData('title');
        if ($customTitle) {
            return $customTitle;
        }

        switch ($this->getRecommendationType()) {
            case PersonalizedRecommendationInterface::TYPE_BROWSING:
                return __('Inspired by Your Browsing')->render();
            case PersonalizedRecommendationInterface::TYPE_PURCHASE:
                return __('Based on Your Past Purchases')->render();
            case PersonalizedRecommendationInterface::TYPE_WISHLIST:
                return __('Inspired by Your Wishlist')->render();
            case PersonalizedRecommendationInterface::TYPE_JUST_FOR_YOU:
                return __('Recommended Just For You')->render();
            default:
                return __('Recommended Products')->render();
        }
    }

    /**
     * Get personalized products
     *
     * @return ProductInterface[]
     */
    public function getProducts(): array
    {
        if ($this->loadedProducts !== null) {
            return $this->loadedProducts;
        }

        if (!$this->config->isEnabled()) {
            $this->loadedProducts = [];
            return $this->loadedProducts;
        }

        $customerId = $this->getCustomerId();
        $limit = $this->getProductLimit();
        $type = $this->getRecommendationType();

        // Get additional product IDs to exclude (current product + cart items)
        $additionalExcludeIds = $this->getExclusionProductIds();

        try {
            switch ($type) {
                case PersonalizedRecommendationInterface::TYPE_BROWSING:
                    $this->loadedProducts = $this->recommendationService->getBrowsingInspired(
                        $customerId,
                        $limit,
                        null,
                        $additionalExcludeIds
                    );
                    break;

                case PersonalizedRecommendationInterface::TYPE_PURCHASE:
                    if ($customerId) {
                        $this->loadedProducts = $this->recommendationService->getPurchaseInspired(
                            $customerId,
                            $limit,
                            null,
                            $additionalExcludeIds
                        );
                    } else {
                        $this->loadedProducts = [];
                    }
                    break;

                case PersonalizedRecommendationInterface::TYPE_WISHLIST:
                    if ($customerId) {
                        $this->loadedProducts = $this->recommendationService->getWishlistInspired(
                            $customerId,
                            $limit,
                            null,
                            $additionalExcludeIds
                        );
                    } else {
                        $this->loadedProducts = [];
                    }
                    break;

                case PersonalizedRecommendationInterface::TYPE_JUST_FOR_YOU:
                    if ($customerId) {
                        $this->loadedProducts = $this->recommendationService->getJustForYou(
                            $customerId,
                            $limit,
                            null,
                            $additionalExcludeIds
                        );
                    } else {
                        // Fall back to browsing for guests
                        $this->loadedProducts = $this->recommendationService->getBrowsingInspired(
                            null,
                            $limit,
                            null,
                            $additionalExcludeIds
                        );
                    }
                    break;

                default:
                    $this->loadedProducts = [];
            }
        } catch (\Exception $e) {
            $this->loadedProducts = [];
        }

        return $this->loadedProducts;
    }

    /**
     * Get product IDs to exclude from recommendations
     *
     * Includes current product (if on product page) and cart items
     *
     * @return array
     */
    private function getExclusionProductIds(): array
    {
        $excludeIds = [];

        try {
            // Get current product ID if on product page (using registry from parent Context)
            $currentProduct = $this->_registry->registry('current_product');
            if ($currentProduct && $currentProduct->getId()) {
                $excludeIds[] = (int) $currentProduct->getId();
            }
        } catch (\Exception $e) {
            // Log but continue - registry might not be available in all contexts
            if ($this->config->isDebugMode()) {
                $this->logger->debug('[ProductRecommendation] Error getting current product: ' . $e->getMessage());
            }
        }

        // Get cart product IDs
        try {
            $quote = $this->checkoutSession->getQuote();
            if ($quote && $quote->getId()) {
                $cartItems = $quote->getAllVisibleItems();
                foreach ($cartItems as $item) {
                    $product = $item->getProduct();
                    if ($product && $product->getId()) {
                        $excludeIds[] = (int) $product->getId();
                    }
                }
            }
        } catch (\Exception $e) {
            // Log but continue - cart might be empty or unavailable
            if ($this->config->isDebugMode()) {
                $this->logger->debug('[ProductRecommendation] Error getting cart items: ' . $e->getMessage());
            }
        }

        return array_unique($excludeIds);
    }

    /**
     * Check if block has products to display
     *
     * @return bool
     */
    public function hasProducts(): bool
    {
        return !empty($this->getProducts());
    }

    /**
     * Check if block should be displayed
     *
     * @return bool
     */
    public function canDisplay(): bool
    {
        if (!$this->config->isEnabled()) {
            return false;
        }

        $type = $this->getRecommendationType();
        $customerId = $this->getCustomerId();

        // Check if we have enough data
        if (!$this->recommendationService->hasEnoughData($customerId, $type)) {
            return false;
        }

        return $this->hasProducts();
    }

    /**
     * Get current customer ID
     *
     * @return int|null
     */
    public function getCustomerId(): ?int
    {
        if ($this->customerSession->isLoggedIn()) {
            return (int) $this->customerSession->getCustomerId();
        }
        return null;
    }

    /**
     * Check if customer is logged in
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return $this->customerSession->isLoggedIn();
    }

    /**
     * Get icon class for block
     *
     * @return string
     */
    public function getIconClass(): string
    {
        switch ($this->getRecommendationType()) {
            case PersonalizedRecommendationInterface::TYPE_BROWSING:
                return 'icon-eye';
            case PersonalizedRecommendationInterface::TYPE_PURCHASE:
                return 'icon-shopping-bag';
            case PersonalizedRecommendationInterface::TYPE_WISHLIST:
                return 'icon-heart';
            case PersonalizedRecommendationInterface::TYPE_JUST_FOR_YOU:
                return 'icon-star';
            default:
                return 'icon-gift';
        }
    }

    /**
     * Get post parameters for adding product to cart
     *
     * @param ProductInterface $product
     * @return array|null
     */
    public function getAddToCartPostParams(ProductInterface $product): ?array
    {
        try {
            $url = $this->getAddToCartUrl($product);
            $currentUrl = $this->_urlBuilder->getCurrentUrl();
            return [
                'action' => $url,
                'data' => [
                    'product' => (int)$product->getEntityId(),
                    \Magento\Framework\App\ActionInterface::PARAM_NAME_URL_ENCODED =>
                        $this->urlEncoder->encode($currentUrl)
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get loaded product collection
     *
     * Converts product array to collection for compatibility with Magento's special price resolver
     *
     * @return ProductCollection
     */
    public function getLoadedProductCollection(): ProductCollection
    {
        if ($this->productCollection !== null) {
            return $this->productCollection;
        }

        $products = $this->getProducts();
        $this->productCollection = $this->productCollectionFactory->create();

        if (!empty($products)) {
            $productIds = array_map(function ($product) {
                return $product->getId();
            }, $products);

            $this->productCollection->addIdFilter($productIds);
            $this->productCollection->addStoreFilter($this->_storeManager->getStore()->getId());

            // Add all necessary attributes for configurable products
            $this->productCollection->addAttributeToSelect('*');
            $this->productCollection->addMinimalPrice();
            $this->productCollection->addFinalPrice();
            $this->productCollection->addTaxPercents();

            // Maintain original order from recommendation service
            $this->productCollection->getSelect()->order(
                new \Zend_Db_Expr('FIELD(e.entity_id, ' . implode(',', $productIds) . ')')
            );

            // Explicitly load the collection
            $this->productCollection->load();
        }

        return $this->productCollection;
    }

    /**
     * Get price render block
     *
     * Don't mark as product list to avoid special price map issues with configurable products
     *
     * @return \Magento\Framework\Pricing\Render|false
     */
    protected function getPriceRender()
    {
        // Use parent implementation which doesn't set is_product_list
        // This ensures each product price is calculated individually
        // avoiding "Undefined array key" errors with special_price_map
        return parent::getPriceRender();
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml(): string
    {
        if (!$this->canDisplay()) {
            return '';
        }

        return parent::_toHtml();
    }
}
