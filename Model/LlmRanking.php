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

namespace NavinDBhudiya\ProductRecommendation\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Stdlib\DateTime\DateTime;
use NavinDBhudiya\ProductRecommendation\Api\Data\LlmRankingInterface;

/**
 * LLM Ranking Model
 */
class LlmRanking extends AbstractModel implements LlmRankingInterface
{
    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param DateTime $dateTime
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        DateTime $dateTime,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->dateTime = $dateTime;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(\NavinDBhudiya\ProductRecommendation\Model\ResourceModel\LlmRanking::class);
    }

    /**
     * @inheritDoc
     */
    public function getId()
    {
        return $this->getData(self::ID) ? (int)$this->getData(self::ID) : null;
    }

    /**
     * @inheritDoc
     */
    public function setId($id)
    {
        return $this->setData(self::ID, $id);
    }

    /**
     * @inheritDoc
     */
    public function getCustomerId(): ?int
    {
        return $this->getData(self::CUSTOMER_ID) ? (int)$this->getData(self::CUSTOMER_ID) : null;
    }

    /**
     * @inheritDoc
     */
    public function setCustomerId(?int $customerId): LlmRankingInterface
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    /**
     * @inheritDoc
     */
    public function getProductId(): int
    {
        return (int)$this->getData(self::PRODUCT_ID);
    }

    /**
     * @inheritDoc
     */
    public function setProductId(int $productId): LlmRankingInterface
    {
        return $this->setData(self::PRODUCT_ID, $productId);
    }

    /**
     * @inheritDoc
     */
    public function getRecommendationType(): string
    {
        return (string)$this->getData(self::RECOMMENDATION_TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setRecommendationType(string $type): LlmRankingInterface
    {
        return $this->setData(self::RECOMMENDATION_TYPE, $type);
    }

    /**
     * @inheritDoc
     */
    public function getStoreId(): int
    {
        return (int)$this->getData(self::STORE_ID);
    }

    /**
     * @inheritDoc
     */
    public function setStoreId(int $storeId): LlmRankingInterface
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getRankedProductIds(): array
    {
        $data = $this->getData(self::RANKED_PRODUCT_IDS);
        if (is_string($data)) {
            return json_decode($data, true) ?: [];
        }
        return is_array($data) ? $data : [];
    }

    /**
     * @inheritDoc
     */
    public function setRankedProductIds(array $productIds): LlmRankingInterface
    {
        return $this->setData(self::RANKED_PRODUCT_IDS, json_encode($productIds));
    }

    /**
     * @inheritDoc
     */
    public function getRankingMetadata(): array
    {
        $data = $this->getData(self::RANKING_METADATA);
        if (is_string($data)) {
            return json_decode($data, true) ?: [];
        }
        return is_array($data) ? $data : [];
    }

    /**
     * @inheritDoc
     */
    public function setRankingMetadata(array $metadata): LlmRankingInterface
    {
        return $this->setData(self::RANKING_METADATA, json_encode($metadata));
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): string
    {
        return (string)$this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(string $createdAt): LlmRankingInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getExpiresAt(): string
    {
        return (string)$this->getData(self::EXPIRES_AT);
    }

    /**
     * @inheritDoc
     */
    public function setExpiresAt(string $expiresAt): LlmRankingInterface
    {
        return $this->setData(self::EXPIRES_AT, $expiresAt);
    }

    /**
     * @inheritDoc
     */
    public function getModelUsed(): ?string
    {
        return $this->getData(self::MODEL_USED) ?: null;
    }

    /**
     * @inheritDoc
     */
    public function setModelUsed(string $model): LlmRankingInterface
    {
        return $this->setData(self::MODEL_USED, $model);
    }

    /**
     * @inheritDoc
     */
    public function getEstimatedCost(): ?float
    {
        $cost = $this->getData(self::ESTIMATED_COST);
        return $cost !== null ? (float)$cost : null;
    }

    /**
     * @inheritDoc
     */
    public function setEstimatedCost(float $cost): LlmRankingInterface
    {
        return $this->setData(self::ESTIMATED_COST, $cost);
    }

    /**
     * @inheritDoc
     */
    public function isExpired(): bool
    {
        $expiresAt = $this->getExpiresAt();
        if (!$expiresAt) {
            return true;
        }

        $currentTime = $this->dateTime->gmtTimestamp();
        $expirationTime = strtotime($expiresAt);

        return $currentTime >= $expirationTime;
    }
}