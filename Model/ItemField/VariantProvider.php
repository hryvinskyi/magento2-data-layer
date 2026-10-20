<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\ItemField;

use Hryvinskyi\DataLayer\Api\Product\LineVariantResolverInterface;
use Hryvinskyi\DataLayerApi\Api\ItemFieldProviderInterface;

/**
 * Fills `item_variant` with the options chosen on the item's cart or order line; a catalogue item has none
 */
class VariantProvider implements ItemFieldProviderInterface
{
    /**
     * @param LineVariantResolverInterface $variants
     */
    public function __construct(
        private readonly LineVariantResolverInterface $variants
    ) {
    }

    /**
     * @inheritDoc
     */
    public function provide(array $subjects, string $eventName): array
    {
        $fields = [];
        foreach ($subjects as $key => $subject) {
            $line = $subject->getOrderItem() ?? $subject->getQuoteItem();
            $variant = $line !== null ? $this->variants->variant($line) : null;
            if ($variant !== null && $variant !== '') {
                $fields[$key] = ['item_variant' => $variant];
            }
        }

        return $fields;
    }
}
