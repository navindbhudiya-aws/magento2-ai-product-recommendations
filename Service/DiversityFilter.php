<?php
/**
 * NavinDBhudiya ProductRecommendation
 *
 * Diversity Filter - Ensures variety in recommendations
 * Prevents showing 5 red shoes - ensures customers see variety
 */

declare(strict_types=1);

namespace NavinDBhudiya\ProductRecommendation\Service;

use Magento\Catalog\Api\Data\ProductInterface;
use NavinDBhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

class DiversityFilter
{
    private const MAX_PER_CATEGORY = 3;
    private const MAX_PER_BRAND = 2;
    private const MAX_PER_COLOR = 3;

    private Config $config;
    private LoggerInterface $logger;

    public function __construct(
        Config $config,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Filter products to ensure diversity
     *
     * @param ProductInterface[] $products Products sorted by score
     * @param int $limit Maximum products to return
     * @param int $maxPerCategory Max products from same category
     * @param int $maxPerBrand Max products from same brand
     * @return ProductInterface[]
     */
    public function filter(
        array $products,
        int $limit,
        int $maxPerCategory = self::MAX_PER_CATEGORY,
        int $maxPerBrand = self::MAX_PER_BRAND
    ): array {
        if (empty($products) || $limit <= 0) {
            return [];
        }

        $this->log("Filtering " . count($products) . " products for diversity (limit: {$limit})");

        $categoryCount = [];
        $brandCount = [];
        $colorCount = [];
        $filtered = [];

        foreach ($products as $product) {
            if (count($filtered) >= $limit) {
                break;
            }

            $productId = (int) $product->getId();

            // Check category limit
            $categoryIds = $product->getCategoryIds();
            $primaryCategory = !empty($categoryIds) ? (int) $categoryIds[0] : 0;
            
            if ($primaryCategory > 0) {
                if (($categoryCount[$primaryCategory] ?? 0) >= $maxPerCategory) {
                    $this->log("Skip product {$productId}: category {$primaryCategory} limit reached");
                    continue;
                }
            }

            // Check brand limit
            $brand = $this->getAttribute($product, 'manufacturer');
            if ($brand && ($brandCount[$brand] ?? 0) >= $maxPerBrand) {
                $this->log("Skip product {$productId}: brand '{$brand}' limit reached");
                continue;
            }

            // Check color limit
            $color = $this->getAttribute($product, 'color');
            if ($color && ($colorCount[$color] ?? 0) >= self::MAX_PER_COLOR) {
                $this->log("Skip product {$productId}: color '{$color}' limit reached");
                continue;
            }

            // Product passes - add it
            $filtered[] = $product;

            // Update counts
            if ($primaryCategory > 0) {
                $categoryCount[$primaryCategory] = ($categoryCount[$primaryCategory] ?? 0) + 1;
            }
            if ($brand) {
                $brandCount[$brand] = ($brandCount[$brand] ?? 0) + 1;
            }
            if ($color) {
                $colorCount[$color] = ($colorCount[$color] ?? 0) + 1;
            }
        }

        $this->log("Diversity filter: " . count($filtered) . " products selected");
        
        return $filtered;
    }

    /**
     * Get diversity score for products (0 = no diversity, 1 = max diversity)
     */
    public function getDiversityScore(array $products): float
    {
        if (count($products) <= 1) {
            return 1.0;
        }

        $categories = [];
        $brands = [];

        foreach ($products as $product) {
            $categoryIds = $product->getCategoryIds();
            if (!empty($categoryIds)) {
                $categories[] = (int) $categoryIds[0];
            }
            $brand = $this->getAttribute($product, 'manufacturer');
            if ($brand) {
                $brands[] = $brand;
            }
        }

        $categoryDiversity = !empty($categories) 
            ? count(array_unique($categories)) / count($categories) 
            : 1.0;
        
        $brandDiversity = !empty($brands) 
            ? count(array_unique($brands)) / count($brands) 
            : 1.0;

        return round(($categoryDiversity * 0.6) + ($brandDiversity * 0.4), 4);
    }

    /**
     * Get product attribute value
     */
    private function getAttribute(ProductInterface $product, string $code): ?string
    {
        try {
            $value = $product->getData($code);
            if ($value === null || $value === '') {
                return null;
            }

            if (method_exists($product, 'getAttributeText')) {
                $text = $product->getAttributeText($code);
                if ($text && !is_array($text)) {
                    return (string) $text;
                }
            }

            return (string) $value;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function log(string $message): void
    {
        if ($this->config->isDebugMode()) {
            $this->logger->debug('[ProductRecommendation] DiversityFilter: ' . $message);
        }
    }
}
