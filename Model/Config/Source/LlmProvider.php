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
 * LLM Provider options for admin configuration
 */
class LlmProvider implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'claude', 'label' => __('Anthropic Claude (Recommended)')],
            ['value' => 'openai', 'label' => __('OpenAI GPT-4')],
        ];
    }
}