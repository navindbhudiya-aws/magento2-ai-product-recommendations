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

namespace NavinDBhudiya\ProductRecommendation\Service\Llm;

use GuzzleHttp\Client;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use NavinDBhudiya\ProductRecommendation\Api\LlmProviderInterface;
use NavinDBhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * OpenAI API provider for LLM-based re-ranking
 *
 * Supports GPT-4, GPT-4 Turbo, GPT-3.5 Turbo, and other OpenAI models
 */
class OpenAiProvider implements LlmProviderInterface
{
    /**
     * OpenAI API endpoint
     */
    private const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * Default model
     */
    private const DEFAULT_MODEL = 'gpt-4-turbo-preview';

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
     * Get HTTP client for OpenAI API
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
                        'Authorization' => 'Bearer ' . $this->config->getLlmApiKey(),
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
            throw new \Exception('OpenAI API is not configured. Please set API key in admin.');
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
                        'role' => 'system',
                        'content' => 'You are an intelligent e-commerce product recommendation system. You analyze products and customer context to provide highly relevant, personalized product recommendations.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
            ];

            $this->log('Sending request to OpenAI API', [
                'model' => $model,
                'prompt_length' => strlen($prompt),
            ]);

            $response = $this->getClient()->post('', [
                'json' => $payload,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (!isset($result['choices'][0]['message']['content'])) {
                throw new \Exception('Invalid response from OpenAI API: ' . json_encode($result));
            }

            $text = $result['choices'][0]['message']['content'];

            $this->log('Received response from OpenAI API', [
                'response_length' => strlen($text),
                'usage' => $result['usage'] ?? [],
            ]);

            return $text;

        } catch (GuzzleException $e) {
            $this->logger->error('[ProductRecommendation] OpenAI API error: ' . $e->getMessage());
            throw new \Exception('OpenAI API request failed: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] OpenAI error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        $apiKey = $this->config->getLlmApiKey();
        return !empty($apiKey) && $this->config->getLlmProvider() === 'openai';
    }

    /**
     * @inheritDoc
     */
    public function getProviderName(): string
    {
        return 'openai';
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
            $this->logger->debug('[ProductRecommendation][OpenAI] ' . $message, $context);
        }
    }
}