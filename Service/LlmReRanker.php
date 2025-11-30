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
use Navindbhudiya\ProductRecommendation\Api\Data\RecommendationResultInterface;
use Navindbhudiya\ProductRecommendation\Api\LlmProviderInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Navindbhudiya\ProductRecommendation\Service\Llm\ClaudeProvider;
use Navindbhudiya\ProductRecommendation\Service\Llm\OpenAiProvider;
use Psr\Log\LoggerInterface;

/**
 * LLM-based intelligent re-ranking service
 */
class LlmReRanker
{
    /**
     * @var ClaudeProvider
     */
    private ClaudeProvider $claudeProvider;

    /**
     * @var OpenAiProvider
     */
    private OpenAiProvider $openAiProvider;

    /**
     * @var ContextBuilder
     */
    private ContextBuilder $contextBuilder;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param ClaudeProvider $claudeProvider
     * @param OpenAiProvider $openAiProvider
     * @param ContextBuilder $contextBuilder
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        ClaudeProvider $claudeProvider,
        OpenAiProvider $openAiProvider,
        ContextBuilder $contextBuilder,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->claudeProvider = $claudeProvider;
        $this->openAiProvider = $openAiProvider;
        $this->contextBuilder = $contextBuilder;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Re-rank recommendation results using LLM
     *
     * @param ProductInterface $sourceProduct
     * @param RecommendationResultInterface[] $candidates
     * @param string $recommendationType
     * @param int|null $customerId
     * @param int $limit
     * @param int|null $storeId
     * @return RecommendationResultInterface[]
     */
    public function rerank(
        ProductInterface $sourceProduct,
        array $candidates,
        string $recommendationType,
        ?int $customerId = null,
        int $limit = 10,
        ?int $storeId = null
    ): array {
        // Check if LLM re-ranking is enabled
        if (!$this->config->isLlmRerankingEnabled($storeId)) {
            return array_slice($candidates, 0, $limit);
        }

        // Get active provider
        $provider = $this->getActiveProvider();
        if (!$provider || !$provider->isAvailable()) {
            $this->log('LLM provider not available, skipping re-ranking');
            return array_slice($candidates, 0, $limit);
        }

        try {
            // Limit candidates to configured count
            $candidateCount = $this->config->getLlmCandidateCount($storeId);
            $candidatesToRerank = array_slice($candidates, 0, $candidateCount);

            if (empty($candidatesToRerank)) {
                return [];
            }

            // Build prompt
            $prompt = $this->buildPrompt(
                $sourceProduct,
                $candidatesToRerank,
                $recommendationType,
                $customerId
            );

            $this->log('Sending re-ranking request to LLM', [
                'provider' => $provider->getProviderName(),
                'candidates' => count($candidatesToRerank),
            ]);

            // Send to LLM
            $response = $provider->sendPrompt($prompt, [
                'temperature' => $this->config->getLlmTemperature($storeId),
                'max_tokens' => 4096,
            ]);

            // Parse response
            $rankings = $this->parseResponse($response);

            // Re-order candidates based on LLM ranking
            $reranked = $this->applyRankings($candidatesToRerank, $rankings);

            $this->log('Successfully re-ranked products', [
                'original_count' => count($candidatesToRerank),
                'reranked_count' => count($reranked),
            ]);

            return array_slice($reranked, 0, $limit);

        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] LLM re-ranking failed: ' . $e->getMessage());
            // Fall back to original ranking
            return array_slice($candidates, 0, $limit);
        }
    }

    /**
     * Build prompt for LLM
     *
     * @param ProductInterface $sourceProduct
     * @param RecommendationResultInterface[] $candidates
     * @param string $recommendationType
     * @param int|null $customerId
     * @return string
     */
    private function buildPrompt(
        ProductInterface $sourceProduct,
        array $candidates,
        string $recommendationType,
        ?int $customerId
    ): string {
        // Build context
        $sourceContext = $this->contextBuilder->buildProductContext($sourceProduct);
        $customerContext = $this->contextBuilder->buildCustomerContext($customerId);
        $contextualFactors = $this->contextBuilder->buildContextualFactors($recommendationType);

        // Build candidate products list
        $candidatesList = [];
        foreach ($candidates as $index => $result) {
            $product = $result->getProduct();
            $productContext = $this->contextBuilder->buildProductContext($product);

            $candidatesList[] = [
                'product_id' => $productContext['id'],
                'name' => $productContext['name'],
                'price' => $productContext['formatted_price'],
                'special_price' => $productContext['formatted_special_price'],
                'has_discount' => $productContext['has_discount'],
                'discount_percentage' => $productContext['discount_percentage'],
                'category' => $productContext['primary_category'],
                'description' => $productContext['description'],
                'similarity_score' => round($result->getScore(), 2),
            ];
        }

        // Build prompt using template
        $prompt = $this->getPromptTemplate();

        // Replace placeholders
        $replacements = [
            '{source_product_name}' => $sourceContext['name'],
            '{source_product_price}' => $sourceContext['formatted_price'],
            '{source_product_category}' => $sourceContext['primary_category'],
            '{customer_segment}' => $customerContext['segment'],
            '{context}' => $contextualFactors['season'] . ' season, ' . $contextualFactors['month'],
            '{recommendation_type}' => $recommendationType,
            '{candidate_products_list}' => json_encode($candidatesList, JSON_PRETTY_PRINT),
        ];

        foreach ($replacements as $placeholder => $value) {
            $prompt = str_replace($placeholder, $value, $prompt);
        }

        return $prompt;
    }

    /**
     * Get prompt template
     *
     * @return string
     */
    private function getPromptTemplate(): string
    {
        return <<<PROMPT
You are an intelligent product re-ranking assistant for an e-commerce store. Your task is to re-order a list of recommended products to maximize relevance and purchase likelihood.

**Context:**
- Source Product: {source_product_name} ({source_product_price})
- Category: {source_product_category}
- Customer Profile: {customer_segment}
- Current Season/Event: {context}
- Recommendation Type: {recommendation_type}

**Candidate Products to Re-rank:**
{candidate_products_list}

**Re-ranking Criteria (in priority order):**
1. **Relevance**: How well does this product complement or relate to the source product?
2. **Purchase Intent**: Based on the customer profile, how likely is a purchase?
3. **Price Compatibility**: Is the price range appropriate for this customer?
4. **Contextual Fit**: Does this product make sense for the current season/event?
5. **Diversity**: Avoid showing too many similar items consecutively.
6. **Value**: Consider discounts and special offers that may increase conversion.

**Output Format:**
Return ONLY a valid JSON array of product IDs in optimal order with reasoning. Do not include any other text.

Example format:
{
  "rankings": [
    {"product_id": 123, "rank": 1, "reason": "Perfect complement, same brand, similar price point, seasonal fit"},
    {"product_id": 456, "rank": 2, "reason": "Frequently bought together, matches customer preference, good value"},
    {"product_id": 789, "rank": 3, "reason": "Good alternative, different category for diversity, has discount"}
  ]
}

Re-rank the products now:
PROMPT;
    }

    /**
     * Parse LLM response
     *
     * @param string $response
     * @return array
     */
    private function parseResponse(string $response): array
    {
        try {
            // Remove markdown code blocks if present
            $response = preg_replace('/```json\s*(.*?)\s*```/s', '$1', $response);
            $response = preg_replace('/```\s*(.*?)\s*```/s', '$1', $response);
            $response = trim($response);

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
            }

            if (!isset($data['rankings']) || !is_array($data['rankings'])) {
                throw new \Exception('Response missing rankings array');
            }

            return $data['rankings'];

        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] Failed to parse LLM response: ' . $e->getMessage());
            $this->logger->debug('[ProductRecommendation] Response was: ' . substr($response, 0, 500));
            return [];
        }
    }

    /**
     * Apply LLM rankings to candidates
     *
     * @param RecommendationResultInterface[] $candidates
     * @param array $rankings
     * @return RecommendationResultInterface[]
     */
    private function applyRankings(array $candidates, array $rankings): array
    {
        if (empty($rankings)) {
            return $candidates;
        }

        // Create a map of product ID to ranking
        $rankingMap = [];
        foreach ($rankings as $ranking) {
            if (isset($ranking['product_id']) && isset($ranking['rank'])) {
                $rankingMap[(int) $ranking['product_id']] = [
                    'rank' => (int) $ranking['rank'],
                    'reason' => $ranking['reason'] ?? '',
                ];
            }
        }

        // Create a map of product ID to candidate
        $candidateMap = [];
        foreach ($candidates as $candidate) {
            $productId = (int) $candidate->getProduct()->getId();
            $candidateMap[$productId] = $candidate;
        }

        // Build re-ordered array
        $reordered = [];
        foreach ($rankingMap as $productId => $rankingInfo) {
            if (isset($candidateMap[$productId])) {
                $candidate = $candidateMap[$productId];

                // Add LLM reason to metadata
                $metadata = $candidate->getMetadata() ?? [];
                $metadata['llm_rank'] = $rankingInfo['rank'];
                $metadata['llm_reason'] = $rankingInfo['reason'];
                $candidate->setMetadata($metadata);

                $reordered[] = $candidate;
            }
        }

        // Add any products that weren't ranked by LLM at the end
        foreach ($candidates as $candidate) {
            $productId = (int) $candidate->getProduct()->getId();
            if (!isset($rankingMap[$productId])) {
                $reordered[] = $candidate;
            }
        }

        return $reordered;
    }

    /**
     * Get active LLM provider
     *
     * @return LlmProviderInterface|null
     */
    private function getActiveProvider(): ?LlmProviderInterface
    {
        $providerName = $this->config->getLlmProvider();

        if ($providerName === 'claude' && $this->claudeProvider->isAvailable()) {
            return $this->claudeProvider;
        }

        if ($providerName === 'openai' && $this->openAiProvider->isAvailable()) {
            return $this->openAiProvider;
        }

        return null;
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
            $this->logger->debug('[ProductRecommendation][LlmReRanker] ' . $message, $context);
        }
    }
}