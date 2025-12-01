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

namespace Navindbhudiya\ProductRecommendation\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * LLM Model options for admin configuration
 */
class LlmModel implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'label' => __('Claude Models'),
                'value' => [
                    ['value' => 'claude-3-5-sonnet-20240620', 'label' => __('Claude 3.5 Sonnet (Recommended)')],
                    ['value' => 'claude-3-opus-20240229', 'label' => __('Claude 3 Opus (Most Capable)')],
                    ['value' => 'claude-3-sonnet-20240229', 'label' => __('Claude 3 Sonnet')],
                    ['value' => 'claude-3-haiku-20240307', 'label' => __('Claude 3 Haiku (Fastest & Cheapest)')],
                ],
            ],
            [
                'label' => __('OpenAI Models'),
                'value' => [
                    ['value' => 'gpt-4-turbo-preview', 'label' => __('GPT-4 Turbo (Recommended)')],
                    ['value' => 'gpt-4', 'label' => __('GPT-4')],
                    ['value' => 'gpt-4-32k', 'label' => __('GPT-4 32K')],
                    ['value' => 'gpt-3.5-turbo', 'label' => __('GPT-3.5 Turbo (Budget)')],
                ],
            ],
        ];
    }
}