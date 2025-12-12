<?php
/**
 * NavinDBhudiya ProductRecommendation
 *
 * CLI command to refresh trending products
 */

declare(strict_types=1);

namespace NavinDBhudiya\ProductRecommendation\Console\Command;

use Magento\Store\Model\StoreManagerInterface;
use NavinDBhudiya\ProductRecommendation\Service\TrendingBooster;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RefreshTrending extends Command
{
    private TrendingBooster $trendingBooster;
    private StoreManagerInterface $storeManager;

    public function __construct(
        TrendingBooster $trendingBooster,
        StoreManagerInterface $storeManager,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->trendingBooster = $trendingBooster;
        $this->storeManager = $storeManager;
    }

    protected function configure()
    {
        $this->setName('recommendation:trending:refresh')
            ->setDescription('Refresh trending products based on recent sales')
            ->addOption('store', 's', InputOption::VALUE_OPTIONAL, 'Store ID', null)
            ->addOption('period', 'p', InputOption::VALUE_OPTIONAL, 'Period in days', 7);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $storeId = $input->getOption('store');
        $periodDays = (int) $input->getOption('period');

        $output->writeln('<info>Refreshing trending products...</info>');
        $output->writeln("<comment>Period: {$periodDays} days</comment>");

        try {
            if ($storeId !== null) {
                $storeId = (int) $storeId;
                $count = $this->trendingBooster->refreshTrendingCache($storeId, $periodDays);
                $output->writeln("<info>Store {$storeId}: {$count} trending products</info>");
            } else {
                $stores = $this->storeManager->getStores();
                $total = 0;
                
                foreach ($stores as $store) {
                    $id = (int) $store->getId();
                    $count = $this->trendingBooster->refreshTrendingCache($id, $periodDays);
                    $total += $count;
                    $output->writeln("<info>Store {$id}: {$count} products</info>");
                }
                
                $output->writeln("<info>Total: {$total} trending products</info>");
            }

            // Show top trending
            $output->writeln('');
            $output->writeln('<comment>Top 10 Trending:</comment>');
            
            $targetStore = $storeId !== null ? (int) $storeId : 1;
            $top = $this->trendingBooster->getTopTrending($targetStore, 10);
            
            if (empty($top)) {
                $output->writeln('<comment>No trending products found.</comment>');
            } else {
                foreach ($top as $productId => $score) {
                    $percent = round($score * 100, 1);
                    $output->writeln("  Product {$productId}: {$percent}%");
                }
            }

            $output->writeln('');
            $output->writeln('<info>Done!</info>');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
