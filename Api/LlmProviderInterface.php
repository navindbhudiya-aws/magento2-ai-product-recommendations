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

namespace NavinDBhudiya\ProductRecommendation\Api;

/**
 * Interface for LLM providers (Claude, OpenAI GPT-4, etc.)
 */
interface LlmProviderInterface
{
    /**
     * Send a prompt to the LLM and get a response
     *
     * @param string $prompt The prompt to send
     * @param array $options Additional options (temperature, max_tokens, etc.)
     * @return string The LLM response
     * @throws \Exception If the request fails
     */
    public function sendPrompt(string $prompt, array $options = []): string;

    /**
     * Check if the LLM provider is available and configured
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Get the provider name
     *
     * @return string
     */
    public function getProviderName(): string;

    /**
     * Get the model being used
     *
     * @return string
     */
    public function getModel(): string;
}