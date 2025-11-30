<?php
/**
 * Navindbhudiya ProductRecommendation
 *
 * Cron job to refresh trending products cache
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Cron;

use Magento\Store\Model\StoreManagerInterface;
use Navindbhudiya\ProductRecommendation\Service\TrendingBooster;
use Navindbhudiya\ProductRecommendation\Helper\Config;
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
        $this->logger->info('[ProductRecommendation] Starting trending refresh cron');

        $stores = $this->storeManager->getStores();
        $total = 0;

        foreach ($stores as $store) {
            $storeId = (int) $store->getId();
            
            if (!$this->config->isEnabled($storeId)) {
                continue;
            }

            try {
                $count = $this->trendingBooster->refreshTrendingCache($storeId, 7);
                $total += $count;
                
                $this->logger->info("[ProductRecommendation] Store {$storeId}: {$count} trending products");
            } catch (\Exception $e) {
                $this->logger->error("[ProductRecommendation] Error for store {$storeId}: " . $e->getMessage());
            }
        }

        $this->logger->info("[ProductRecommendation] Trending refresh done. Total: {$total}");
    }
}
