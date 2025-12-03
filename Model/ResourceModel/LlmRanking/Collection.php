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

namespace NavinDBhudiya\ProductRecommendation\Model\ResourceModel\LlmRanking;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use NavinDBhudiya\ProductRecommendation\Model\LlmRanking as Model;
use NavinDBhudiya\ProductRecommendation\Model\ResourceModel\LlmRanking as ResourceModel;

/**
 * LLM Ranking Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }

    /**
     * Filter by customer ID
     *
     * @param int|null $customerId
     * @return $this
     */
    public function addCustomerFilter(?int $customerId): self
    {
        if ($customerId === null) {
            $this->addFieldToFilter('customer_id', ['null' => true]);
        } else {
            $this->addFieldToFilter('customer_id', $customerId);
        }
        return $this;
    }

    /**
     * Filter by product ID
     *
     * @param int $productId
     * @return $this
     */
    public function addProductFilter(int $productId): self
    {
        $this->addFieldToFilter('product_id', $productId);
        return $this;
    }

    /**
     * Filter by recommendation type
     *
     * @param string $type
     * @return $this
     */
    public function addTypeFilter(string $type): self
    {
        $this->addFieldToFilter('recommendation_type', $type);
        return $this;
    }

    /**
     * Filter by store ID
     *
     * @param int $storeId
     * @return $this
     */
    public function addStoreFilter(int $storeId): self
    {
        $this->addFieldToFilter('store_id', $storeId);
        return $this;
    }

    /**
     * Filter out expired rankings
     *
     * @return $this
     */
    public function addNotExpiredFilter(): self
    {
        $this->addFieldToFilter('expires_at', ['gteq' => gmdate('Y-m-d H:i:s')]);
        return $this;
    }

    /**
     * Filter expired rankings only
     *
     * @return $this
     */
    public function addExpiredFilter(): self
    {
        $this->addFieldToFilter('expires_at', ['lt' => gmdate('Y-m-d H:i:s')]);
        return $this;
    }
}