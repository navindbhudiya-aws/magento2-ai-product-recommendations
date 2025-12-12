<?php
/**
 * NavinDBhudiya ProductRecommendation
 *
 * Recommendation Explainer - Generates explanations like "Recommended because similar features"
 */

declare(strict_types=1);

namespace NavinDBhudiya\ProductRecommendation\Service;

use Magento\Catalog\Api\Data\ProductInterface;
use NavinDBhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

class RecommendationExplainer
{
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
     * Generate explanation for a recommendation
     *
     * @param ProductInterface $sourceProduct The product being viewed
     * @param ProductInterface $recommendedProduct The recommended product
     * @param float $similarityScore Similarity score (0-1)
     * @param string $type Recommendation type (related, upsell, crosssell)
     * @return string Human-readable explanation
     */
    public function explain(
        ProductInterface $sourceProduct,
        ProductInterface $recommendedProduct,
        float $similarityScore,
        string $type = 'related'
    ): string {
        $factors = $this->analyzeFactors($sourceProduct, $recommendedProduct, $similarityScore);
        
        return $this->buildExplanation($factors, $type);
    }

    /**
     * Explain personalized recommendation
     */
    public function explainPersonalized(string $behaviorType, float $score): string
    {
        $explanations = [
            'browsing' => 'Based on your browsing history',
            'purchase' => 'Based on your past purchases',
            'wishlist' => 'Similar to items in your wishlist',
            'combined' => 'Recommended for you',
            'trending' => 'Trending now'
        ];

        return $explanations[$behaviorType] ?? 'You might also like';
    }

    /**
     * Get detailed explanation with all factors
     *
     * @return array ['explanation' => string, 'factors' => array, 'score' => float]
     */
    public function getDetailedExplanation(
        ProductInterface $source,
        ProductInterface $recommended,
        float $score,
        string $type = 'related'
    ): array {
        $factors = $this->analyzeFactors($source, $recommended, $score);
        
        return [
            'explanation' => $this->buildExplanation($factors, $type),
            'factors' => $factors,
            'score' => round($score, 4),
            'type' => $type
        ];
    }

    /**
     * Analyze factors contributing to recommendation
     */
    private function analyzeFactors(
        ProductInterface $source,
        ProductInterface $recommended,
        float $score
    ): array {
        $factors = [];

        // High similarity score
        if ($score >= 0.8) {
            $factors['semantic'] = [
                'weight' => 0.4,
                'label' => 'very similar features',
                'value' => round($score, 2)
            ];
        } elseif ($score >= 0.6) {
            $factors['semantic'] = [
                'weight' => 0.3,
                'label' => 'similar features',
                'value' => round($score, 2)
            ];
        }

        // Same category
        $sourceCategories = $source->getCategoryIds() ?? [];
        $recommendedCategories = $recommended->getCategoryIds() ?? [];
        $shared = array_intersect($sourceCategories, $recommendedCategories);
        
        if (!empty($shared)) {
            $factors['category'] = [
                'weight' => 0.25,
                'label' => 'same category',
                'value' => count($shared)
            ];
        }

        // Same brand
        $sourceBrand = $this->getAttribute($source, 'manufacturer');
        $recommendedBrand = $this->getAttribute($recommended, 'manufacturer');
        
        if ($sourceBrand && $recommendedBrand && $sourceBrand === $recommendedBrand) {
            $factors['brand'] = [
                'weight' => 0.2,
                'label' => 'same brand',
                'value' => $sourceBrand
            ];
        }

        // Similar price
        $sourcePrice = (float) $source->getPrice();
        $recommendedPrice = (float) $recommended->getPrice();
        
        if ($sourcePrice > 0 && $recommendedPrice > 0) {
            $priceDiff = abs($sourcePrice - $recommendedPrice) / $sourcePrice;
            
            if ($priceDiff <= 0.2) {
                $factors['price'] = [
                    'weight' => 0.15,
                    'label' => 'similar price',
                    'value' => round($priceDiff * 100, 1) . '%'
                ];
            }
        }

        return $factors;
    }

    /**
     * Build explanation string from factors
     */
    private function buildExplanation(array $factors, string $type): string
    {
        // Handle special types
        if ($type === 'upsell') {
            return 'Premium alternative';
        }
        if ($type === 'crosssell') {
            return 'Complements your selection';
        }
        if ($type === 'trending') {
            return 'Trending now';
        }

        if (empty($factors)) {
            return 'You might also like';
        }

        // Sort by weight
        uasort($factors, fn($a, $b) => $b['weight'] <=> $a['weight']);

        // Get top 2 factors
        $parts = [];
        $count = 0;
        
        foreach ($factors as $key => $factor) {
            if ($count >= 2) break;
            $parts[] = $factor['label'];
            $count++;
        }

        if (empty($parts)) {
            return 'You might also like';
        }

        return 'Recommended because: ' . implode(', ', $parts);
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
}
