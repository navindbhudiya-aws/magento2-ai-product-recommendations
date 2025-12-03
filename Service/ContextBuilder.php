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

namespace NavinDBhudiya\ProductRecommendation\Service;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds rich context for LLM prompts
 */
class ContextBuilder
{
    /**
     * @var CategoryRepositoryInterface
     */
    private CategoryRepositoryInterface $categoryRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    private CustomerRepositoryInterface $customerRepository;

    /**
     * @var PriceCurrencyInterface
     */
    private PriceCurrencyInterface $priceCurrency;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param CategoryRepositoryInterface $categoryRepository
     * @param CustomerRepositoryInterface $customerRepository
     * @param PriceCurrencyInterface $priceCurrency
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        CustomerRepositoryInterface $customerRepository,
        PriceCurrencyInterface $priceCurrency,
        StoreManagerInterface $storeManager
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->customerRepository = $customerRepository;
        $this->priceCurrency = $priceCurrency;
        $this->storeManager = $storeManager;
    }

    /**
     * Build context for a product
     *
     * @param ProductInterface $product
     * @return array
     */
    public function buildProductContext(ProductInterface $product): array
    {
        $categoryNames = $this->getProductCategories($product);
        $price = (float) $product->getPrice();
        $specialPrice = $product->getSpecialPrice() ? (float) $product->getSpecialPrice() : null;

        return [
            'id' => (int) $product->getId(),
            'name' => $product->getName(),
            'sku' => $product->getSku(),
            'price' => $price,
            'formatted_price' => $this->formatPrice($price),
            'special_price' => $specialPrice,
            'formatted_special_price' => $specialPrice ? $this->formatPrice($specialPrice) : null,
            'has_discount' => $specialPrice && $specialPrice < $price,
            'discount_percentage' => $specialPrice && $specialPrice < $price
                ? round((($price - $specialPrice) / $price) * 100)
                : 0,
            'categories' => $categoryNames,
            'primary_category' => $categoryNames[0] ?? 'Uncategorized',
            'description' => $this->cleanText($product->getShortDescription() ?? $product->getDescription()),
            'type' => $product->getTypeId(),
        ];
    }

    /**
     * Build customer context
     *
     * @param int|null $customerId
     * @return array
     */
    public function buildCustomerContext(?int $customerId): array
    {
        if (!$customerId) {
            return [
                'segment' => 'guest',
                'is_logged_in' => false,
            ];
        }

        try {
            $customer = $this->customerRepository->getById($customerId);

            return [
                'segment' => $this->determineCustomerSegment($customer),
                'is_logged_in' => true,
                'group' => $customer->getGroupId(),
            ];
        } catch (\Exception $e) {
            return [
                'segment' => 'unknown',
                'is_logged_in' => false,
            ];
        }
    }

    /**
     * Build contextual factors
     *
     * @param string $recommendationType
     * @param string|null $pageType
     * @return array
     */
    public function buildContextualFactors(string $recommendationType, ?string $pageType = null): array
    {
        $season = $this->getCurrentSeason();
        $month = date('F');

        return [
            'recommendation_type' => $recommendationType,
            'page_type' => $pageType ?? 'unknown',
            'season' => $season,
            'month' => $month,
            'is_holiday_season' => $this->isHolidaySeason(),
            'time_of_day' => $this->getTimeOfDay(),
        ];
    }

    /**
     * Get product categories
     *
     * @param ProductInterface $product
     * @return array
     */
    private function getProductCategories(ProductInterface $product): array
    {
        $categoryIds = $product->getCategoryIds();
        $categoryNames = [];

        foreach ($categoryIds as $categoryId) {
            try {
                $category = $this->categoryRepository->get($categoryId);
                $categoryNames[] = $category->getName();
            } catch (\Exception $e) {
                // Skip invalid categories
            }
        }

        return $categoryNames;
    }

    /**
     * Determine customer segment based on behavior
     *
     * @param CustomerInterface $customer
     * @return string
     */
    private function determineCustomerSegment(CustomerInterface $customer): string
    {
        // Simple segmentation - can be enhanced with actual order data
        $groupId = $customer->getGroupId();

        switch ($groupId) {
            case 1: // General
                return 'regular_customer';
            case 2: // Wholesale
                return 'wholesale_buyer';
            case 3: // Retailer
                return 'retailer';
            default:
                return 'standard_customer';
        }
    }

    /**
     * Get current season
     *
     * @return string
     */
    private function getCurrentSeason(): string
    {
        $month = (int) date('n');

        if ($month >= 3 && $month <= 5) {
            return 'spring';
        } elseif ($month >= 6 && $month <= 8) {
            return 'summer';
        } elseif ($month >= 9 && $month <= 11) {
            return 'fall';
        } else {
            return 'winter';
        }
    }

    /**
     * Check if it's holiday season
     *
     * @return bool
     */
    private function isHolidaySeason(): bool
    {
        $month = (int) date('n');
        $day = (int) date('j');

        // November-December (Thanksgiving, Black Friday, Christmas, New Year)
        if ($month >= 11) {
            return true;
        }

        // Valentine's Day period
        if ($month === 2 && $day >= 1 && $day <= 14) {
            return true;
        }

        return false;
    }

    /**
     * Get time of day
     *
     * @return string
     */
    private function getTimeOfDay(): string
    {
        $hour = (int) date('G');

        if ($hour >= 5 && $hour < 12) {
            return 'morning';
        } elseif ($hour >= 12 && $hour < 17) {
            return 'afternoon';
        } elseif ($hour >= 17 && $hour < 21) {
            return 'evening';
        } else {
            return 'night';
        }
    }

    /**
     * Format price with currency
     *
     * @param float $price
     * @return string
     */
    private function formatPrice(float $price): string
    {
        try {
            return $this->priceCurrency->format(
                $price,
                false,
                PriceCurrencyInterface::DEFAULT_PRECISION,
                $this->storeManager->getStore()
            );
        } catch (\Exception $e) {
            return '$' . number_format($price, 2);
        }
    }

    /**
     * Clean text for LLM consumption
     *
     * @param string|null $text
     * @return string
     */
    private function cleanText(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Remove HTML tags
        $text = strip_tags($text);

        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Trim
        $text = trim($text);

        // Limit length
        if (strlen($text) > 200) {
            $text = substr($text, 0, 197) . '...';
        }

        return $text;
    }
}