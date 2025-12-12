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

namespace NavinDBhudiya\ProductRecommendation\Test\Unit\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use NavinDBhudiya\ProductRecommendation\Helper\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for Config helper
 */
class ConfigTest extends TestCase
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $scopeConfigMock;

    /**
     * @var EncryptorInterface|MockObject
     */
    private $encryptorMock;

    /**
     * @var Context|MockObject
     */
    private $contextMock;

    /**
     * Set up test
     */
    protected function setUp(): void
    {
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->encryptorMock = $this->createMock(EncryptorInterface::class);
        $this->contextMock = $this->createMock(Context::class);

        $this->contextMock->method('getScopeConfig')
            ->willReturn($this->scopeConfigMock);

        $this->config = new Config(
            $this->contextMock,
            $this->encryptorMock
        );
    }

    /**
     * Test isEnabled returns true when module is enabled
     */
    public function testIsEnabledReturnsTrue(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with('product_recommendation/general/enabled')
            ->willReturn(true);

        $this->assertTrue($this->config->isEnabled());
    }

    /**
     * Test isEnabled returns false when module is disabled
     */
    public function testIsEnabledReturnsFalse(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with('product_recommendation/general/enabled')
            ->willReturn(false);

        $this->assertFalse($this->config->isEnabled());
    }

    /**
     * Test getChromaDbHost returns correct value
     */
    public function testGetChromaDbHost(): void
    {
        $expectedHost = 'chromadb';

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('product_recommendation/chromadb/host')
            ->willReturn($expectedHost);

        $this->assertEquals($expectedHost, $this->config->getChromaDbHost());
    }

    /**
     * Test getChromaDbPort returns correct value
     */
    public function testGetChromaDbPort(): void
    {
        $expectedPort = 8000;

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('product_recommendation/chromadb/port')
            ->willReturn($expectedPort);

        $this->assertEquals($expectedPort, $this->config->getChromaDbPort());
    }

    /**
     * Test getCollectionName returns correct value
     */
    public function testGetCollectionName(): void
    {
        $expectedName = 'magento_products';

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('product_recommendation/chromadb/collection_name')
            ->willReturn($expectedName);

        $this->assertEquals($expectedName, $this->config->getCollectionName());
    }

    /**
     * Test isDebugMode returns true when debug is enabled
     */
    public function testIsDebugModeEnabled(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with('product_recommendation/general/debug_mode')
            ->willReturn(true);

        $this->assertTrue($this->config->isDebugMode());
    }

    /**
     * Test getSimilarityThreshold returns correct value
     */
    public function testGetSimilarityThreshold(): void
    {
        $expectedThreshold = 0.75;

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('product_recommendation/recommendation/similarity_threshold')
            ->willReturn($expectedThreshold);

        $this->assertEquals($expectedThreshold, $this->config->getSimilarityThreshold());
    }

    /**
     * Test isCacheEnabled returns true when cache is enabled
     */
    public function testIsCacheEnabled(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with('product_recommendation/cache/enabled')
            ->willReturn(true);

        $this->assertTrue($this->config->isCacheEnabled());
    }

    /**
     * Test getCacheLifetime returns correct value
     */
    public function testGetCacheLifetime(): void
    {
        $expectedLifetime = 3600;

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('product_recommendation/cache/lifetime')
            ->willReturn($expectedLifetime);

        $this->assertEquals($expectedLifetime, $this->config->getCacheLifetime());
    }

    /**
     * Test isLlmReRankingEnabled returns true when LLM is enabled
     */
    public function testIsLlmReRankingEnabled(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with('product_recommendation/llm_reranking/enabled')
            ->willReturn(true);

        $this->assertTrue($this->config->isLlmReRankingEnabled());
    }

    /**
     * Test getLlmProvider returns correct provider
     */
    public function testGetLlmProvider(): void
    {
        $expectedProvider = 'claude';

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('product_recommendation/llm_reranking/provider')
            ->willReturn($expectedProvider);

        $this->assertEquals($expectedProvider, $this->config->getLlmProvider());
    }

    /**
     * Test getLlmApiKey returns decrypted API key
     */
    public function testGetLlmApiKey(): void
    {
        $encryptedKey = 'encrypted_key';
        $decryptedKey = 'sk-ant-api-key';

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('product_recommendation/llm_reranking/api_key')
            ->willReturn($encryptedKey);

        $this->encryptorMock->expects($this->once())
            ->method('decrypt')
            ->with($encryptedKey)
            ->willReturn($decryptedKey);

        $this->assertEquals($decryptedKey, $this->config->getLlmApiKey());
    }

    /**
     * Test getLlmModel returns correct model
     */
    public function testGetLlmModel(): void
    {
        $expectedModel = 'claude-sonnet-4-5-20250929';

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('product_recommendation/llm_reranking/model')
            ->willReturn($expectedModel);

        $this->assertEquals($expectedModel, $this->config->getLlmModel());
    }
}