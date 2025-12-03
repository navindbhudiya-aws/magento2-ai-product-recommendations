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

namespace NavinDBhudiya\ProductRecommendation\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * LLM Ranking Resource Model
 */
class LlmRanking extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('navindbhudiya_product_recommendation_llm_ranking', 'id');
    }

    /**
     * Delete expired rankings
     *
     * @return int Number of deleted records
     */
    public function deleteExpired(): int
    {
        $connection = $this->getConnection();
        $table = $this->getMainTable();

        $where = ['expires_at < ?' => gmdate('Y-m-d H:i:s')];

        return $connection->delete($table, $where);
    }

    /**
     * Delete rankings for specific customer
     *
     * @param int $customerId
     * @return int Number of deleted records
     */
    public function deleteByCustomer(int $customerId): int
    {
        $connection = $this->getConnection();
        $table = $this->getMainTable();

        $where = ['customer_id = ?' => $customerId];

        return $connection->delete($table, $where);
    }

    /**
     * Delete rankings for specific product
     *
     * @param int $productId
     * @return int Number of deleted records
     */
    public function deleteByProduct(int $productId): int
    {
        $connection = $this->getConnection();
        $table = $this->getMainTable();

        $where = ['product_id = ?' => $productId];

        return $connection->delete($table, $where);
    }
}