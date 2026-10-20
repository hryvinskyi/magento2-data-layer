<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit;

use Magento\Framework\Phrase\RendererInterface;

/**
 * Renders phrases in a made-up locale: each text through the dictionary given, then its `%1` to `%n` placeholders
 *
 * A text the dictionary does not hold renders as it is written.
 */
class DictionaryRenderer implements RendererInterface
{
    /**
     * @param array<string, string> $dictionary Translations keyed by source text
     */
    public function __construct(
        private readonly array $dictionary
    ) {
    }

    /**
     * Translate the last text of the chain and fill its placeholders
     *
     * @param array<mixed> $source
     * @param array<mixed> $arguments
     * @return string
     */
    public function render(array $source, array $arguments)
    {
        $last = end($source);
        $text = is_scalar($last) || $last instanceof \Stringable ? (string) $last : '';
        $text = $this->dictionary[$text] ?? $text;
        foreach (array_values($arguments) as $index => $argument) {
            $value = is_scalar($argument) || $argument instanceof \Stringable ? (string) $argument : '';
            $text = str_replace('%' . ($index + 1), $value, $text);
        }

        return $text;
    }
}
