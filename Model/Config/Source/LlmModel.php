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

namespace NavinDBhudiya\ProductRecommendation\Model\Config\Source;

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
            ['value' => 'claude-opus-4-5-20251101', 'label' => __('Claude Opus 4.5 (Premium - Maximum Intelligence)')],
            ['value' => 'claude-sonnet-4-5-20250929', 'label' => __('Claude Sonnet 4.5 (Recommended - Best for Agents & Coding)')],
            ['value' => 'claude-haiku-4-5-20251001', 'label' => __('Claude Haiku 4.5 (Fastest - Near-Frontier Intelligence)')],
            ['value' => 'claude-3-5-sonnet-20241022', 'label' => __('Claude 3.5 Sonnet (Legacy)')],
            ['value' => 'claude-3-5-sonnet-20240620', 'label' => __('Claude 3.5 Sonnet (Legacy - Jun 2024)')],
            ['value' => 'claude-3-5-haiku-20241022', 'label' => __('Claude 3.5 Haiku (Legacy)')],
            ['value' => 'claude-3-opus-20240229', 'label' => __('Claude 3 Opus (Legacy)')],
        ];
    }
}