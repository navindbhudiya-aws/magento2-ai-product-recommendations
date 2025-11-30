<?php
/**
 * Navindbhudiya ProductRecommendation
 *
 * @category  Navindbhudiya
 * @package   Navindbhudiya_ProductRecommendation
 * @author    Navin Bhudiya
 * @license   MIT License
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Service\Llm;

use GuzzleHttp\Client;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use Navindbhudiya\ProductRecommendation\Api\LlmProviderInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Claude API provider for LLM-based re-ranking
 *
 * Supports Claude 3.5 Sonnet, Claude 3 Opus, and other Anthropic models
 */
class ClaudeProvider implements LlmProviderInterface
{
    /**
     * Claude API endpoint
     */
    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

    /**
     * API version
     */
    private const API_VERSION = '2023-06-01';

    /**
     * Default model
     */
    private const DEFAULT_MODEL = 'claude-3-5-sonnet-20241022';

    /**
     * @var ClientFactory
     */
    private ClientFactory $clientFactory;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var Client|null
     */
    private ?Client $client = null;

    /**
     * @param ClientFactory $clientFactory
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        ClientFactory $clientFactory,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->clientFactory = $clientFactory;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Get HTTP client for Claude API
     *
     * @return Client
     */
    private function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = $this->clientFactory->create([
                'config' => [
                    'base_uri' => self::API_ENDPOINT,
                    'timeout' => 30,
                    'connect_timeout' => 10,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'x-api-key' => $this->config->getLlmApiKey(),
                        'anthropic-version' => self::API_VERSION,
                    ],
                ],
            ]);
        }
        return $this->client;
    }

    /**
     * @inheritDoc
     */
    public function sendPrompt(string $prompt, array $options = []): string
    {
        if (!$this->isAvailable()) {
            throw new \Exception('Claude API is not configured. Please set API key in admin.');
        }

        try {
            $model = $options['model'] ?? $this->config->getLlmModel() ?? self::DEFAULT_MODEL;
            $temperature = $options['temperature'] ?? 0.7;
            $maxTokens = $options['max_tokens'] ?? 4096;

            $payload = [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ];

            $this->log('Sending request to Claude API', [
                'model' => $model,
                'prompt_length' => strlen($prompt),
            ]);

            $response = $this->getClient()->post('', [
                'json' => $payload,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (!isset($result['content'][0]['text'])) {
                throw new \Exception('Invalid response from Claude API: ' . json_encode($result));
            }

            $text = $result['content'][0]['text'];

            $this->log('Received response from Claude API', [
                'response_length' => strlen($text),
                'usage' => $result['usage'] ?? [],
            ]);

            return $text;

        } catch (GuzzleException $e) {
            $this->logger->error('[ProductRecommendation] Claude API error: ' . $e->getMessage());
            throw new \Exception('Claude API request failed: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] Claude error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        $apiKey = $this->config->getLlmApiKey();
        return !empty($apiKey) && $this->config->getLlmProvider() === 'claude';
    }

    /**
     * @inheritDoc
     */
    public function getProviderName(): string
    {
        return 'claude';
    }

    /**
     * @inheritDoc
     */
    public function getModel(): string
    {
        return $this->config->getLlmModel() ?? self::DEFAULT_MODEL;
    }

    /**
     * Log message if debug mode is enabled
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    private function log(string $message, array $context = []): void
    {
        if ($this->config->isDebugMode()) {
            $this->logger->debug('[ProductRecommendation][Claude] ' . $message, $context);
        }
    }
}