<?php
/**
 * NavinDBhudiya ProductRecommendation
 *
 * Trending Booster - Factors in recent sales velocity to boost trending products
 */

declare(strict_types=1);

namespace NavinDBhudiya\ProductRecommendation\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use NavinDBhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

class TrendingBooster
{
    private const TABLE_TRENDING = 'navindbhudiya_ai_trending_products';
    private const DEFAULT_PERIOD_DAYS = 7;
    private const DEFAULT_BOOST_WEIGHT = 0.20; // 20% boost for trending

    private ResourceConnection $resourceConnection;
    private DateTime $dateTime;
    private Config $config;
    private LoggerInterface $logger;
    private array $scoreCache = [];

    public function __construct(
        ResourceConnection $resourceConnection,
        DateTime $dateTime,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->dateTime = $dateTime;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Get trending scores for all products
     *
     * @param int $storeId
     * @param int $periodDays
     * @return array [product_id => normalized_score (0-1)]
     */
    public function getTrendingScores(int $storeId, int $periodDays = self::DEFAULT_PERIOD_DAYS): array
    {
        $cacheKey = "{$storeId}_{$periodDays}";
        
        if (isset($this->scoreCache[$cacheKey])) {
            return $this->scoreCache[$cacheKey];
        }

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(self::TABLE_TRENDING);

        if (!$connection->isTableExists($tableName)) {
            return [];
        }

        $select = $connection->select()
            ->from($tableName, ['product_id', 'trend_score'])
            ->where('store_id = ?', $storeId)
            ->where('period_days = ?', $periodDays);

        $results = $connection->fetchPairs($select);

        $scores = [];
        foreach ($results as $productId => $score) {
            $scores[(int) $productId] = (float) $score;
        }

        $this->scoreCache[$cacheKey] = $scores;
        
        return $scores;
    }

    /**
     * Apply trending boost to recommendation scores
     *
     * @param array $scores [product_id => original_score]
     * @param int $storeId
     * @param float $boostWeight Weight of trending factor (0-1)
     * @return array [product_id => boosted_score]
     */
    public function applyTrendingBoost(
        array $scores,
        int $storeId,
        float $boostWeight = self::DEFAULT_BOOST_WEIGHT
    ): array {
        if (empty($scores) || $boostWeight <= 0) {
            return $scores;
        }

        $trendingScores = $this->getTrendingScores($storeId);
        
        if (empty($trendingScores)) {
            return $scores;
        }

        $boostedScores = [];
        
        foreach ($scores as $productId => $originalScore) {
            $trendScore = $trendingScores[$productId] ?? 0;
            
            // Boost formula: final = original * (1 + trend_score * boost_weight)
            $boost = 1 + ($trendScore * $boostWeight);
            $boostedScores[$productId] = $originalScore * $boost;
        }

        // Re-sort by boosted scores
        arsort($boostedScores);

        $this->log("Applied trending boost to " . count($boostedScores) . " products");

        return $boostedScores;
    }

    /**
     * Refresh trending products cache from sales data
     *
     * @param int $storeId
     * @param int $periodDays
     * @return int Number of products processed
     */
    public function refreshTrendingCache(int $storeId, int $periodDays = self::DEFAULT_PERIOD_DAYS): int
    {
        $connection = $this->resourceConnection->getConnection();
        
        $this->log("Refreshing trending cache for store {$storeId}, period: {$periodDays} days");

        // Calculate date range
        $endDate = $this->dateTime->gmtDate('Y-m-d H:i:s');
        $startDate = $this->dateTime->gmtDate('Y-m-d H:i:s', strtotime("-{$periodDays} days"));

        // Get sales data
        $orderItemTable = $this->resourceConnection->getTableName('sales_order_item');
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $select = $connection->select()
            ->from(
                ['oi' => $orderItemTable],
                [
                    'product_id',
                    'order_count' => new \Zend_Db_Expr('COUNT(DISTINCT oi.order_id)'),
                    'qty_sold' => new \Zend_Db_Expr('SUM(oi.qty_ordered)')
                ]
            )
            ->join(
                ['o' => $orderTable],
                'oi.order_id = o.entity_id',
                []
            )
            ->where('o.store_id = ?', $storeId)
            ->where('o.created_at >= ?', $startDate)
            ->where('o.created_at <= ?', $endDate)
            ->where('oi.product_type = ?', 'simple')
            ->group('oi.product_id');

        $salesData = $connection->fetchAll($select);

        if (empty($salesData)) {
            $this->log("No sales data found for trending calculation");
            return 0;
        }

        // Calculate trend scores
        $rawScores = [];
        foreach ($salesData as $row) {
            $productId = (int) $row['product_id'];
            $orderCount = (int) $row['order_count'];
            $qtySold = (float) $row['qty_sold'];
            
            // Weight orders more (repeat purchases indicate demand)
            $rawScores[$productId] = ($orderCount * 1.5) + $qtySold;
        }

        // Normalize to 0-1
        $maxScore = max($rawScores);
        $normalizedScores = [];
        
        foreach ($rawScores as $productId => $score) {
            $normalizedScores[$productId] = $maxScore > 0 ? $score / $maxScore : 0;
        }

        // Store in database
        $this->ensureTableExists();
        
        $tableName = $this->resourceConnection->getTableName(self::TABLE_TRENDING);

        // Clear old data
        $connection->delete($tableName, [
            'store_id = ?' => $storeId,
            'period_days = ?' => $periodDays
        ]);

        // Insert new data
        $insertData = [];
        foreach ($normalizedScores as $productId => $score) {
            $insertData[] = [
                'product_id' => $productId,
                'store_id' => $storeId,
                'trend_score' => round($score, 6),
                'sales_count' => 0,
                'period_days' => $periodDays
            ];
        }

        if (!empty($insertData)) {
            $connection->insertMultiple($tableName, $insertData);
        }

        $this->scoreCache = [];

        $this->log("Refreshed trending cache: " . count($insertData) . " products");

        return count($insertData);
    }

    /**
     * Get top trending products
     */
    public function getTopTrending(int $storeId, int $limit = 20): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(self::TABLE_TRENDING);

        if (!$connection->isTableExists($tableName)) {
            return [];
        }

        $select = $connection->select()
            ->from($tableName, ['product_id', 'trend_score'])
            ->where('store_id = ?', $storeId)
            ->order('trend_score DESC')
            ->limit($limit);

        $results = $connection->fetchPairs($select);

        $trending = [];
        foreach ($results as $productId => $score) {
            $trending[(int) $productId] = (float) $score;
        }

        return $trending;
    }

    /**
     * Check if product is trending
     */
    public function isTrending(int $productId, int $storeId): bool
    {
        $scores = $this->getTrendingScores($storeId);
        return isset($scores[$productId]) && $scores[$productId] > 0.3;
    }

    /**
     * Ensure trending table exists
     */
    private function ensureTableExists(): void
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(self::TABLE_TRENDING);

        if ($connection->isTableExists($tableName)) {
            return;
        }

        $table = $connection->newTable($tableName)
            ->addColumn(
                'trending_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true]
            )
            ->addColumn(
                'product_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['unsigned' => true, 'nullable' => false]
            )
            ->addColumn(
                'store_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                null,
                ['unsigned' => true, 'nullable' => false, 'default' => 0]
            )
            ->addColumn(
                'trend_score',
                \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                '12,6',
                ['nullable' => false, 'default' => 0]
            )
            ->addColumn(
                'sales_count',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['unsigned' => true, 'nullable' => false, 'default' => 0]
            )
            ->addColumn(
                'period_days',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['unsigned' => true, 'nullable' => false, 'default' => 7]
            )
            ->addColumn(
                'updated_at',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE]
            )
            ->addIndex(
                'NAVINDBHUDIYA_AI_TRENDING_PRODUCT_STORE_PERIOD',
                ['product_id', 'store_id', 'period_days'],
                ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE]
            )
            ->addIndex('NAVINDBHUDIYA_AI_TRENDING_SCORE', ['trend_score'])
            ->setComment('Trending Products');

        $connection->createTable($table);
        
        $this->log("Created trending products table");
    }

    private function log(string $message): void
    {
        if ($this->config->isDebugMode()) {
            $this->logger->debug('[ProductRecommendation] TrendingBooster: ' . $message);
        }
    }
}
