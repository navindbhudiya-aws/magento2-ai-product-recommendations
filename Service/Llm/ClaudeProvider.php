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
use NavinDBhudiya\ProductRecommendation\Service\CircuitBreaker;
use Psr\Log\LoggerInterface;

/**
 * Claude API provider for LLM-based re-ranking
 *
 * Supports Claude 4.5 Sonnet, Claude 4.5 Opus, Claude 4.1 Opus
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
     * Default model (Claude 3.5 Sonnet)
     */
    private const DEFAULT_MODEL = 'claude-3-5-sonnet-20240620';

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
     * @var CircuitBreaker
     */
    private CircuitBreaker $circuitBreaker;

    /**
     * @param ClientFactory $clientFactory
     * @param Config $config
     * @param LoggerInterface $logger
     * @param CircuitBreaker $circuitBreaker
     */
    public function __construct(
        ClientFactory $clientFactory,
        Config $config,
        LoggerInterface $logger,
        CircuitBreaker $circuitBreaker
    ) {
        $this->clientFactory = $clientFactory;
        $this->config = $config;
        $this->logger = $logger;
        $this->circuitBreaker = $circuitBreaker;
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
                    'timeout' => 5, // Reduced from 30s - max 5 seconds to prevent blocking page load
                    'connect_timeout' => 2, // Reduced from 10s - max 2 seconds to establish connection
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
            $this->log('❌ Claude API is NOT available', [
                'has_api_key' => !empty($this->config->getLlmApiKey()),
                'configured_provider' => $this->config->getLlmProvider()
            ]);
            throw new \Exception('Claude API is not configured. Please set API key in admin.');
        }

        // Check circuit breaker BEFORE making request
        if ($this->circuitBreaker->isOpen('claude_api')) {
            $this->log('⚠️  Circuit breaker OPEN - Claude API calls disabled temporarily', [
                'failure_count' => $this->circuitBreaker->getFailureCount('claude_api'),
                'note' => 'API will be retried after 5 minutes'
            ]);
            throw new \Exception('Claude API temporarily unavailable due to repeated failures');
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

            $this->log('📡 Sending request to Claude API', [
                'model' => $model,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'prompt_length' => strlen($prompt),
                'estimated_input_tokens' => (int)(strlen($prompt) / 4),
                'api_endpoint' => self::API_ENDPOINT,
                'api_version' => self::API_VERSION
            ]);

            $startTime = microtime(true);

            $response = $this->getClient()->post('', [
                'json' => $payload,
            ]);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $result = json_decode($response->getBody()->getContents(), true);

            if (!isset($result['content'][0]['text'])) {
                $this->log('❌ Invalid Claude API response structure', [
                    'response_keys' => array_keys($result),
                    'response' => json_encode($result)
                ]);
                throw new \Exception('Invalid response from Claude API: ' . json_encode($result));
            }

            $text = $result['content'][0]['text'];
            $usage = $result['usage'] ?? [];

            // Calculate estimated cost
            $inputTokens = $usage['input_tokens'] ?? 0;
            $outputTokens = $usage['output_tokens'] ?? 0;
            $estimatedCost = ($inputTokens * 3 / 1000000) + ($outputTokens * 15 / 1000000);

            $this->log('✅ Received response from Claude API', [
                'model_used' => $result['model'] ?? $model,
                'response_length' => strlen($text),
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $inputTokens + $outputTokens,
                'stop_reason' => $result['stop_reason'] ?? 'unknown',
                'request_duration_ms' => $duration,
                'estimated_cost_usd' => sprintf('$%.4f', $estimatedCost),
                'response_preview' => substr($text, 0, 150) . '...'
            ]);

            if ($estimatedCost > 0.10) {
                $this->log('⚠️  HIGH COST WARNING', [
                    'cost' => sprintf('$%.4f', $estimatedCost),
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens
                ]);
            }

            // SUCCESS - Reset circuit breaker
            $this->circuitBreaker->reset('claude_api');

            return $text;

        } catch (GuzzleException $e) {
            // FAILURE - Record in circuit breaker
            $this->circuitBreaker->recordFailure('claude_api');

            $errorBody = method_exists($e, 'getResponse') && $e->getResponse()
                ? $e->getResponse()->getBody()->getContents()
                : '';

            $this->log('❌ Claude API HTTP Error', [
                'error' => $e->getMessage(),
                'error_body' => $errorBody,
                'status_code' => method_exists($e, 'getResponse') && $e->getResponse()
                    ? $e->getResponse()->getStatusCode()
                    : 'unknown',
                'circuit_breaker_failures' => $this->circuitBreaker->getFailureCount('claude_api')
            ]);

            $this->logger->error('[ProductRecommendation][Claude] API error: ' . $e->getMessage());
            throw new \Exception('Claude API request failed: ' . $e->getMessage());
        } catch (\Exception $e) {
            // FAILURE - Record in circuit breaker
            $this->circuitBreaker->recordFailure('claude_api');

            $this->log('❌ Claude Provider Exception', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 300),
                'circuit_breaker_failures' => $this->circuitBreaker->getFailureCount('claude_api')
            ]);
            $this->logger->error('[ProductRecommendation][Claude] error: ' . $e->getMessage());
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