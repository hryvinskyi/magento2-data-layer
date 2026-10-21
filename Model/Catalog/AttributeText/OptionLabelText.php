<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Catalog\AttributeText;

use Hryvinskyi\DataLayer\Api\Catalog\AttributeTextReaderInterface;
use Hryvinskyi\DataLayer\Api\Catalog\OptionLabelReaderInterface;

/**
 * Text of an attribute that stores option ids: the store labels of the options, joined with a comma
 *
 * A value holds one option id, or several separated by commas. The labels of every product's options are read in one
 * go; an option without a label is skipped.
 */
class OptionLabelText implements AttributeTextReaderInterface
{
    private const SEPARATOR = ', ';

    /**
     * @param OptionLabelReaderInterface $optionLabels
     */
    public function __construct(private readonly OptionLabelReaderInterface $optionLabels)
    {
    }

    /**
     * @inheritDoc
     */
    public function texts(string $attributeCode, array $values, int $storeId): array
    {
        $optionIds = [];
        foreach ($values as $productId => $value) {
            $optionIds[$productId] = array_values(array_filter(
                array_map('trim', explode(',', $value)),
                static fn (string $optionId): bool => $optionId !== ''
            ));
        }
        if ($optionIds === []) {
            return [];
        }

        $labels = $this->optionLabels->labels(
            $attributeCode,
            array_values(array_unique(array_merge(...array_values($optionIds)))),
            $storeId
        );

        $texts = [];
        foreach ($optionIds as $productId => $ids) {
            $names = [];
            foreach ($ids as $optionId) {
                $label = trim($labels[$optionId] ?? '');
                if ($label !== '') {
                    $names[] = $label;
                }
            }
            if ($names !== []) {
                $texts[$productId] = implode(self::SEPARATOR, $names);
            }
        }

        return $texts;
    }
}
