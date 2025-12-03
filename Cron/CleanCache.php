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

namespace NavinDBhudiya\ProductRecommendation\Cron;

use NavinDBhudiya\ProductRecommendation\Api\RecommendationServiceInterface;
use NavinDBhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Cron job to clean expired recommendation cache
 */
class CleanCache
{
    /**
     * @var RecommendationServiceInterface
     */
    private RecommendationServiceInterface $recommendationService;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param RecommendationServiceInterface $recommendationService
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        RecommendationServiceInterface $recommendationService,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->recommendationService = $recommendationService;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->isCacheEnabled()) {
            return;
        }

        try {
            $this->recommendationService->clearAllCache();
        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] Cache cleanup failed: ' . $e->getMessage());
        }
    }
}
