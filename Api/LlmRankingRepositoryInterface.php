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

namespace NavinDBhudiya\ProductRecommendation\Api;

use NavinDBhudiya\ProductRecommendation\Api\Data\LlmRankingInterface;

/**
 * LLM Ranking Repository Interface
 */
interface LlmRankingRepositoryInterface
{
    /**
     * Save LLM ranking
     *
     * @param LlmRankingInterface $ranking
     * @return LlmRankingInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(LlmRankingInterface $ranking): LlmRankingInterface;

    /**
     * Get LLM ranking by ID
     *
     * @param int $id
     * @return LlmRankingInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): LlmRankingInterface;

    /**
     * Get ranking for specific product and customer
     *
     * @param int $productId
     * @param string $recommendationType
     * @param int|null $customerId NULL for default/guest rankings
     * @param int $storeId
     * @return LlmRankingInterface|null
     */
    public function getByProductAndCustomer(
        int $productId,
        string $recommendationType,
        ?int $customerId,
        int $storeId
    ): ?LlmRankingInterface;

    /**
     * Delete LLM ranking
     *
     * @param LlmRankingInterface $ranking
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(LlmRankingInterface $ranking): bool;

    /**
     * Delete LLM ranking by ID
     *
     * @param int $id
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $id): bool;

    /**
     * Delete all expired rankings
     *
     * @return int Number of deleted records
     */
    public function deleteExpired(): int;

    /**
     * Delete all rankings for a specific customer
     *
     * @param int $customerId
     * @return int Number of deleted records
     */
    public function deleteByCustomer(int $customerId): int;

    /**
     * Delete all rankings for a specific product
     *
     * @param int $productId
     * @return int Number of deleted records
     */
    public function deleteByProduct(int $productId): int;
}