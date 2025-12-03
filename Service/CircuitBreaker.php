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

namespace NavinDBhudiya\ProductRecommendation\Service;

use Magento\Framework\App\CacheInterface;
use Psr\Log\LoggerInterface;

/**
 * Circuit Breaker Pattern
 *
 * Prevents cascading failures by temporarily disabling failing services
 */
class CircuitBreaker
{
    /**
     * Number of failures before circuit opens
     */
    private const FAILURE_THRESHOLD = 5;

    /**
     * How long circuit stays open (seconds)
     */
    private const TIMEOUT_DURATION = 300; // 5 minutes

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param CacheInterface $cache
     * @param LoggerInterface $logger
     */
    public function __construct(
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Check if circuit breaker is open (service disabled)
     *
     * @param string $service Service identifier (e.g., 'claude_api')
     * @return bool True if circuit is open (service calls should be blocked)
     */
    public function isOpen(string $service): bool
    {
        $failures = (int)$this->cache->load($this->getFailureKey($service));
        $isOpen = $failures >= self::FAILURE_THRESHOLD;

        if ($isOpen) {
            $this->logger->warning('[ProductRecommendation][CircuitBreaker] Circuit OPEN for service: ' . $service, [
                'failures' => $failures,
                'threshold' => self::FAILURE_THRESHOLD,
                'service' => $service
            ]);
        }

        return $isOpen;
    }

    /**
     * Record a failure for the service
     *
     * @param string $service
     * @return void
     */
    public function recordFailure(string $service): void
    {
        $key = $this->getFailureKey($service);
        $failures = (int)$this->cache->load($key) + 1;

        $this->cache->save(
            (string)$failures,
            $key,
            [],
            self::TIMEOUT_DURATION
        );

        if ($failures >= self::FAILURE_THRESHOLD) {
            $this->logger->error('[ProductRecommendation][CircuitBreaker] Circuit OPENED for service: ' . $service, [
                'failures' => $failures,
                'timeout_duration' => self::TIMEOUT_DURATION . ' seconds',
                'service' => $service
            ]);
        }
    }

    /**
     * Reset circuit breaker (service is healthy again)
     *
     * @param string $service
     * @return void
     */
    public function reset(string $service): void
    {
        $key = $this->getFailureKey($service);
        $this->cache->remove($key);
    }

    /**
     * Get cache key for failure count
     *
     * @param string $service
     * @return string
     */
    private function getFailureKey(string $service): string
    {
        return 'circuit_breaker_' . $service . '_failures';
    }

    /**
     * Get current failure count
     *
     * @param string $service
     * @return int
     */
    public function getFailureCount(string $service): int
    {
        return (int)$this->cache->load($this->getFailureKey($service));
    }
}