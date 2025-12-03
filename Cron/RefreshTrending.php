<?php
/**
 * NavinDBhudiya ProductRecommendation
 *
 * Cron job to refresh trending products cache
 */

declare(strict_types=1);

namespace NavinDBhudiya\ProductRecommendation\Cron;

use Magento\Store\Model\StoreManagerInterface;
use NavinDBhudiya\ProductRecommendation\Service\TrendingBooster;
use NavinDBhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

class RefreshTrending
{
    private TrendingBooster $trendingBooster;
    private StoreManagerInterface $storeManager;
    private Config $config;
    private LoggerInterface $logger;

    public function __construct(
        TrendingBooster $trendingBooster,
        StoreManagerInterface $storeManager,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->trendingBooster = $trendingBooster;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        $stores = $this->storeManager->getStores();

        foreach ($stores as $store) {
            $storeId = (int) $store->getId();

            if (!$this->config->isEnabled($storeId)) {
                continue;
            }

            try {
                $this->trendingBooster->refreshTrendingCache($storeId, 7);
            } catch (\Exception $e) {
                $this->logger->error("[ProductRecommendation] Error for store {$storeId}: " . $e->getMessage());
            }
        }
    }
}
