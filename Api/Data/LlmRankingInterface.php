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

namespace NavinDBhudiya\ProductRecommendation\Api\Data;

/**
 * LLM Ranking Data Interface
 */
interface LlmRankingInterface
{
    public const ID = 'id';
    public const CUSTOMER_ID = 'customer_id';
    public const PRODUCT_ID = 'product_id';
    public const RECOMMENDATION_TYPE = 'recommendation_type';
    public const STORE_ID = 'store_id';
    public const RANKED_PRODUCT_IDS = 'ranked_product_ids';
    public const RANKING_METADATA = 'ranking_metadata';
    public const CREATED_AT = 'created_at';
    public const EXPIRES_AT = 'expires_at';
    public const MODEL_USED = 'model_used';
    public const ESTIMATED_COST = 'estimated_cost';

    /**
     * Get ID
     *
     * @return int|null
     */
    public function getId();

    /**
     * Set ID
     *
     * @param mixed $id
     * @return $this
     */
    public function setId($id);

    /**
     * Get Customer ID
     *
     * @return int|null
     */
    public function getCustomerId(): ?int;

    /**
     * Set Customer ID
     *
     * @param int|null $customerId
     * @return $this
     */
    public function setCustomerId(?int $customerId): self;

    /**
     * Get Product ID
     *
     * @return int
     */
    public function getProductId(): int;

    /**
     * Set Product ID
     *
     * @param int $productId
     * @return $this
     */
    public function setProductId(int $productId): self;

    /**
     * Get Recommendation Type
     *
     * @return string
     */
    public function getRecommendationType(): string;

    /**
     * Set Recommendation Type
     *
     * @param string $type
     * @return $this
     */
    public function setRecommendationType(string $type): self;

    /**
     * Get Store ID
     *
     * @return int
     */
    public function getStoreId(): int;

    /**
     * Set Store ID
     *
     * @param int $storeId
     * @return $this
     */
    public function setStoreId(int $storeId): self;

    /**
     * Get Ranked Product IDs (JSON array)
     *
     * @return array
     */
    public function getRankedProductIds(): array;

    /**
     * Set Ranked Product IDs
     *
     * @param array $productIds
     * @return $this
     */
    public function setRankedProductIds(array $productIds): self;

    /**
     * Get Ranking Metadata (JSON)
     *
     * @return array
     */
    public function getRankingMetadata(): array;

    /**
     * Set Ranking Metadata
     *
     * @param array $metadata
     * @return $this
     */
    public function setRankingMetadata(array $metadata): self;

    /**
     * Get Created At
     *
     * @return string
     */
    public function getCreatedAt(): string;

    /**
     * Set Created At
     *
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt(string $createdAt): self;

    /**
     * Get Expires At
     *
     * @return string
     */
    public function getExpiresAt(): string;

    /**
     * Set Expires At
     *
     * @param string $expiresAt
     * @return $this
     */
    public function setExpiresAt(string $expiresAt): self;

    /**
     * Get Model Used
     *
     * @return string|null
     */
    public function getModelUsed(): ?string;

    /**
     * Set Model Used
     *
     * @param string $model
     * @return $this
     */
    public function setModelUsed(string $model): self;

    /**
     * Get Estimated Cost
     *
     * @return float|null
     */
    public function getEstimatedCost(): ?float;

    /**
     * Set Estimated Cost
     *
     * @param float $cost
     * @return $this
     */
    public function setEstimatedCost(float $cost): self;

    /**
     * Check if ranking is expired
     *
     * @return bool
     */
    public function isExpired(): bool;
}