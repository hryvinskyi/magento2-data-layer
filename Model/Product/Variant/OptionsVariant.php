<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Product\Variant;

use Hryvinskyi\DataLayer\Api\Product\ChosenOptionsInterface;
use Hryvinskyi\DataLayer\Api\Product\LineVariantStrategyInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Sales\Api\Data\OrderItemInterface;

/**
 * The variant of a line as its options list it
 *
 * A cart line's options are those the store's product configuration lists for the line's product type; an order
 * line's are read from the product option groups this strategy is configured with, in that order (for a configurable
 * line the chosen attributes come before the custom options).
 */
class OptionsVariant implements LineVariantStrategyInterface
{
    /**
     * @var list<string>
     */
    private readonly array $orderGroups;

    /**
     * @param ChosenOptionsInterface $chosenOptions
     * @param array<mixed> $orderGroups The order line's product option groups to read, in order
     * @throws \InvalidArgumentException When a group is not a name
     */
    public function __construct(
        private readonly ChosenOptionsInterface $chosenOptions,
        array $orderGroups = ['options', 'additional_options']
    ) {
        $groups = [];
        foreach ($orderGroups as $group) {
            if (!is_string($group) || $group === '') {
                throw new \InvalidArgumentException('Each product option group must be a non-empty name.');
            }
            $groups[] = $group;
        }
        $this->orderGroups = $groups;
    }

    /**
     * @inheritDoc
     */
    public function variant(CartItemInterface|OrderItemInterface $line): ?string
    {
        return $this->chosenOptions->toText(
            $line instanceof OrderItemInterface
                ? $this->chosenOptions->ofOrderLine($line, $this->orderGroups)
                : $this->chosenOptions->ofCartLine($line)
        );
    }
}
