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

namespace Navindbhudiya\ProductRecommendation\Model;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Navindbhudiya\ProductRecommendation\Api\Data\LlmRankingInterface;
use Navindbhudiya\ProductRecommendation\Api\Data\LlmRankingInterfaceFactory;
use Navindbhudiya\ProductRecommendation\Api\LlmRankingRepositoryInterface;
use Navindbhudiya\ProductRecommendation\Model\ResourceModel\LlmRanking as ResourceModel;
use Navindbhudiya\ProductRecommendation\Model\ResourceModel\LlmRanking\CollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * LLM Ranking Repository
 */
class LlmRankingRepository implements LlmRankingRepositoryInterface
{
    /**
     * @var ResourceModel
     */
    private ResourceModel $resourceModel;

    /**
     * @var LlmRankingInterfaceFactory
     */
    private LlmRankingInterfaceFactory $rankingFactory;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var array
     */
    private array $instancesById = [];

    /**
     * @param ResourceModel $resourceModel
     * @param LlmRankingInterfaceFactory $rankingFactory
     * @param CollectionFactory $collectionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResourceModel $resourceModel,
        LlmRankingInterfaceFactory $rankingFactory,
        CollectionFactory $collectionFactory,
        LoggerInterface $logger
    ) {
        $this->resourceModel = $resourceModel;
        $this->rankingFactory = $rankingFactory;
        $this->collectionFactory = $collectionFactory;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function save(LlmRankingInterface $ranking): LlmRankingInterface
    {
        try {
            $this->resourceModel->save($ranking);

            if ($ranking->getId()) {
                $this->instancesById[$ranking->getId()] = $ranking;
            }

            return $ranking;
        } catch (\Exception $e) {
            $this->logger->error(
                '[ProductRecommendation][LlmRankingRepository] Could not save ranking: ' . $e->getMessage(),
                ['exception' => $e]
            );
            throw new CouldNotSaveException(
                __('Could not save LLM ranking: %1', $e->getMessage()),
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getById(int $id): LlmRankingInterface
    {
        if (isset($this->instancesById[$id])) {
            return $this->instancesById[$id];
        }

        $ranking = $this->rankingFactory->create();
        $this->resourceModel->load($ranking, $id);

        if (!$ranking->getId()) {
            throw new NoSuchEntityException(__('LLM ranking with ID "%1" does not exist.', $id));
        }

        $this->instancesById[$id] = $ranking;
        return $ranking;
    }

    /**
     * @inheritDoc
     */
    public function getByProductAndCustomer(
        int $productId,
        string $recommendationType,
        ?int $customerId,
        int $storeId
    ): ?LlmRankingInterface {
        $collection = $this->collectionFactory->create();
        $collection
            ->addProductFilter($productId)
            ->addTypeFilter($recommendationType)
            ->addStoreFilter($storeId)
            ->addCustomerFilter($customerId)
            ->addNotExpiredFilter()
            ->setOrder('created_at', 'DESC')
            ->setPageSize(1);

        $ranking = $collection->getFirstItem();

        if (!$ranking->getId()) {
            return null;
        }

        return $ranking;
    }

    /**
     * @inheritDoc
     */
    public function delete(LlmRankingInterface $ranking): bool
    {
        try {
            $rankingId = $ranking->getId();
            $this->resourceModel->delete($ranking);

            if ($rankingId && isset($this->instancesById[$rankingId])) {
                unset($this->instancesById[$rankingId]);
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error(
                '[ProductRecommendation][LlmRankingRepository] Could not delete ranking: ' . $e->getMessage(),
                ['exception' => $e]
            );
            throw new CouldNotDeleteException(
                __('Could not delete LLM ranking: %1', $e->getMessage()),
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $id): bool
    {
        return $this->delete($this->getById($id));
    }

    /**
     * @inheritDoc
     */
    public function deleteExpired(): int
    {
        try {
            $count = $this->resourceModel->deleteExpired();

            $this->logger->info(
                '[ProductRecommendation][LlmRankingRepository] Deleted expired rankings',
                ['count' => $count]
            );

            return $count;
        } catch (\Exception $e) {
            $this->logger->error(
                '[ProductRecommendation][LlmRankingRepository] Error deleting expired rankings: ' . $e->getMessage(),
                ['exception' => $e]
            );
            return 0;
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteByCustomer(int $customerId): int
    {
        try {
            $count = $this->resourceModel->deleteByCustomer($customerId);

            $this->logger->info(
                '[ProductRecommendation][LlmRankingRepository] Deleted customer rankings',
                ['customer_id' => $customerId, 'count' => $count]
            );

            return $count;
        } catch (\Exception $e) {
            $this->logger->error(
                '[ProductRecommendation][LlmRankingRepository] Error deleting customer rankings: ' . $e->getMessage(),
                ['customer_id' => $customerId, 'exception' => $e]
            );
            return 0;
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteByProduct(int $productId): int
    {
        try {
            $count = $this->resourceModel->deleteByProduct($productId);

            $this->logger->info(
                '[ProductRecommendation][LlmRankingRepository] Deleted product rankings',
                ['product_id' => $productId, 'count' => $count]
            );

            return $count;
        } catch (\Exception $e) {
            $this->logger->error(
                '[ProductRecommendation][LlmRankingRepository] Error deleting product rankings: ' . $e->getMessage(),
                ['product_id' => $productId, 'exception' => $e]
            );
            return 0;
        }
    }
}